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

final readonly class ProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeRepository $attributes,
        private TagRepository $tags,
        private AttributeValueWriter $writer,
    ) {
    }

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

                $this->writer->write($profile->addAttribute($attribute), $value);
            }
        }

        $profile->touch();

        return $this->flush($profile);
    }

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

    /** @param list<string> $names */
    private function syncTags(Project $project, array $names): void
    {
        $wanted = [];

        foreach ($names as $name) {
            $tag = $this->resolveTag($name);
            $wanted[Tag::normalize($name)] = $tag;
        }

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
            $this->em->refresh($profile);

            throw new ConflictException($profile->getVersion());
        }

        return $profile;
    }
}
