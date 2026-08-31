<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Dto\SaveProfileRequest;
use App\Dto\SaveProjectRequest;
use App\Entity\Attribute;
use App\Entity\Profile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Exception\ConflictException;
use App\Repository\AttributeRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Write side of the personal profile.
 *
 * Every mutation goes through the same optimistic-locking gate: the client
 * sends the version it last saw, and a mismatch is refused rather than
 * silently overwriting whatever another tab wrote in the meantime.
 */
final readonly class ProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeRepository $attributes,
        private TagRepository $tags,
        private AttributeValueWriter $writer,
    ) {
    }

    /**
     * An autosave tick: apply every value the client holds, then bump the
     * version once for the whole batch.
     */
    public function save(Profile $profile, SaveProfileRequest $request): Profile
    {
        $this->assertVersion($profile, $request->version);

        $values = $request->normalizedValues();

        if ([] !== $values) {
            $byId = $this->loadAttributes(array_keys($values));

            foreach ($values as $attributeId => $value) {
                $attribute = $byId[$attributeId] ?? null;

                if (null === $attribute) {
                    throw new BadRequestHttpException(sprintf('Атрибут #%d не найден.', $attributeId));
                }

                // Writing a value is what attaches a library attribute to the
                // profile, so an absent one is created rather than rejected —
                // this is also the path CV in-place editing will take.
                $this->writer->write($profile->addAttribute($attribute), $value);
            }
        }

        // Touch unconditionally: @Version only increments when Doctrine sees a
        // change, and a tick that rewrote identical values must still hand the
        // client a fresh version to keep saving against.
        $profile->touch();

        return $this->flush($profile);
    }

    /**
     * Attaches a library attribute with no value yet — the "add attribute"
     * button of the Info section.
     */
    public function addAttribute(Profile $profile, int $attributeId, int $version): Profile
    {
        $this->assertVersion($profile, $version);

        $attribute = $this->attributes->find($attributeId);

        if (null === $attribute || $attribute->isRemoved()) {
            throw new NotFoundHttpException('Атрибут не найден.');
        }

        $profile->addAttribute($attribute);
        $profile->touch();

        return $this->flush($profile);
    }

    /**
     * Detaches a library attribute. Built-ins refuse to be removed — that is
     * the entity's own rule, we only translate it into a 400.
     */
    public function removeAttribute(Profile $profile, int $attributeId, int $version): Profile
    {
        $this->assertVersion($profile, $version);

        $attribute = $this->attributes->find($attributeId);

        if (null === $attribute) {
            throw new NotFoundHttpException('Атрибут не найден.');
        }

        try {
            $profile->removeAttribute($attribute);
        } catch (\LogicException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $profile->touch();

        return $this->flush($profile);
    }

    public function createProject(Profile $profile, SaveProjectRequest $request): Project
    {
        $project = new Project($profile, $request->name);
        // Append: new projects go last, matching the order the list shows.
        $project->setSortOrder($profile->getProjects()->count());

        $this->applyProject($project, $request);

        $profile->addProject($project);
        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }

    public function updateProject(Project $project, SaveProjectRequest $request): Project
    {
        $project->setName($request->name);
        $this->applyProject($project, $request);

        $project->getProfile()->touch();
        $this->em->flush();

        return $project;
    }

    public function deleteProject(Project $project): void
    {
        // Tag counters are maintained by the caller by design, so releasing
        // them here is what keeps the tag cloud honest after a delete.
        foreach ($project->getTags() as $tag) {
            $tag->decrementUsage();
        }

        $profile = $project->getProfile();
        $profile->removeProject($project);

        $this->em->remove($project);
        $this->em->flush();
    }

    private function applyProject(Project $project, SaveProjectRequest $request): void
    {
        $project->setDescription($request->description);

        try {
            $project->setPeriod($request->periodFromDate(), $request->periodToDate());
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $this->syncTags($project, $request->cleanTags());
    }

    /**
     * Brings the project's tags in line with what was submitted, adjusting the
     * denormalised usage counters as links appear and disappear.
     *
     * @param list<string> $names
     */
    private function syncTags(Project $project, array $names): void
    {
        $wanted = [];

        foreach ($names as $name) {
            $tag = $this->resolveTag($name);
            $wanted[Tag::normalize($name)] = $tag;
        }

        // Remove first, so a rename does not briefly double-count.
        foreach ($project->getTags()->toArray() as $tag) {
            if (!isset($wanted[$tag->getNameNormalized()]) && $project->removeTag($tag)) {
                $tag->decrementUsage();
            }
        }

        foreach ($wanted as $tag) {
            if ($project->addTag($tag)) {
                $tag->incrementUsage();
            }
        }
    }

    /**
     * Finds the tag by its normalised name or creates it — this is what makes
     * the tag input free-form while keeping "React" and "react" one tag.
     */
    private function resolveTag(string $name): Tag
    {
        $tag = $this->tags->findOneByName($name);

        if (null === $tag) {
            $tag = new Tag($name);
            $this->em->persist($tag);
        }

        return $tag;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Attribute>
     */
    private function loadAttributes(array $ids): array
    {
        /** @var list<Attribute> $found */
        $found = $this->attributes->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];

        foreach ($found as $attribute) {
            $byId[$attribute->getId()] = $attribute;
        }

        return $byId;
    }

    /**
     * The client's view of the record must match the server's before we write.
     * Checked here as well as by @Version below: this catches the stale write
     * before it touches anything, and gives a message naming the real version.
     */
    private function assertVersion(Profile $profile, int $version): void
    {
        if ($profile->getVersion() !== $version) {
            throw new ConflictException($profile->getVersion());
        }
    }

    private function flush(Profile $profile): Profile
    {
        try {
            $this->em->flush();
        } catch (OptimisticLockException) {
            // Two saves that both passed the check above and raced to the
            // database; the version column is what actually decides.
            $this->em->refresh($profile);

            throw new ConflictException($profile->getVersion());
        }

        return $profile;
    }
}
