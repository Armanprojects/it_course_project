<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\Cv;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\UserRole;

/**
 * Renders a CV: the candidate's profile seen through a position's template.
 *
 * Nothing here is stored — the values are read live from the profile, which is
 * what makes editing an attribute in a CV change the profile itself.
 */
final readonly class CvSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(Cv $cv, ?User $viewer = null): array
    {
        $profile  = $cv->getProfile();
        $position = $cv->getPosition();

        $sections = [];

        foreach ($position->getAttributes() as $link) {
            $attribute = $link->getAttribute();
            $value     = $profile->getValueFor($attribute);
            $section   = $link->getSection() ?? $attribute->getCategory()->value;

            $sections[$section][] = [
                'attributeId' => $attribute->getId(),
                'name'        => $attribute->getName(),
                'type'        => $attribute->getType()->value,
                'options'     => $attribute->getOptions(),
                'required'    => $link->isRequired(),
                'value'       => null === $value ? null : $this->readValue($value),
                // Empty values are highlighted in red by the brief, in both
                // the candidate's editor and the recruiter's read-only view.
                'empty'       => null === $value || $value->isEmpty(),
            ];
        }

        $rendered = [];

        foreach ($sections as $name => $rows) {
            $rendered[] = ['section' => $name, 'attributes' => $rows];
        }

        return [
            'id'          => $cv->getId(),
            'status'      => $cv->getStatus()->value,
            'complete'    => $cv->isComplete(),
            'likesCount'  => $cv->getLikesCount(),
            'likedByMe'   => null !== $viewer && $cv->isLikedBy($viewer),
            'canLike'     => $this->canLike($viewer),
            // Publishing belongs to the owner (and to an admin acting as one),
            // never to a recruiter — the client must not infer this from
            // canLike, since an admin has both.
            'canEdit'     => null !== $viewer
                && ($cv->getCandidate() === $viewer || $viewer->hasRole(UserRole::Admin)),
            'createdAt'   => $cv->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt'   => $cv->getUpdatedAt()->format(\DATE_ATOM),
            'publishedAt' => $cv->getPublishedAt()?->format(\DATE_ATOM),
            'candidate'   => [
                'profileId' => $profile->getId(),
                'userId'    => $profile->getUser()->getId(),
                'email'     => $profile->getUser()->getEmail(),
                'name'      => $this->candidateName($cv),
            ],
            'position' => [
                'id'      => $position->getId(),
                'title'   => $position->getTitle(),
                'company' => $position->getCompany(),
                'level'   => $position->getLevel(),
            ],
            'sections' => $rendered,
            'projects' => array_map(
                $this->serializeProject(...),
                $cv->getRelevantProjects(),
            ),
            'missing' => array_map(
                static fn ($attribute): string => $attribute->getName(),
                $cv->getMissingAttributes(),
            ),
        ];
    }

    /**
     * A compact row for the CV tables: position lists, search results and the
     * candidate's own CV list.
     *
     * @return array<string, mixed>
     */
    public function serializeRow(Cv $cv, ?User $viewer = null): array
    {
        return [
            'id'         => $cv->getId(),
            'status'     => $cv->getStatus()->value,
            'likesCount' => $cv->getLikesCount(),
            'likedByMe'  => null !== $viewer && $cv->isLikedBy($viewer),
            'updatedAt'  => $cv->getUpdatedAt()->format(\DATE_ATOM),
            'candidate'  => [
                'profileId' => $cv->getProfile()->getId(),
                'email'     => $cv->getCandidate()->getEmail(),
                'name'      => $this->candidateName($cv),
            ],
            'position' => [
                'id'      => $cv->getPosition()->getId(),
                'title'   => $cv->getPosition()->getTitle(),
                'company' => $cv->getPosition()->getCompany(),
            ],
        ];
    }

    /**
     * Built from the built-in name attributes, falling back to the address:
     * a CV row with no name at all would be unusable in a recruiter's table.
     */
    private function candidateName(Cv $cv): string
    {
        $profile = $cv->getProfile();
        $parts   = [];

        foreach ($profile->getAttributeValues() as $value) {
            $name = mb_strtolower($value->getAttribute()->getName());

            if (\in_array($name, ['first name', 'last name'], true) && !$value->isEmpty()) {
                $parts[$name] = (string) $value->getValueString();
            }
        }

        $full = trim(($parts['first name'] ?? '') . ' ' . ($parts['last name'] ?? ''));

        return '' !== $full ? $full : $cv->getCandidate()->getEmail();
    }

    private function canLike(?User $viewer): bool
    {
        return null !== $viewer
            && ($viewer->hasRole(UserRole::Recruiter) || $viewer->hasRole(UserRole::Admin));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        return [
            'id'          => $project->getId(),
            'name'        => $project->getName(),
            'description' => $project->getDescription(),
            'periodFrom'  => $project->getPeriodFrom()?->format('Y-m-d'),
            'periodTo'    => $project->getPeriodTo()?->format('Y-m-d'),
            'ongoing'     => $project->isOngoing(),
            'tags'        => array_map(
                static fn ($tag): array => ['id' => $tag->getId(), 'name' => $tag->getName()],
                $project->getTags()->toArray(),
            ),
        ];
    }

    private function readValue(\App\Entity\AttributeValue $value): mixed
    {
        if ($value->isEmpty()) {
            return null;
        }

        return match ($value->getType()) {
            AttributeType::String  => $value->getValueString(),
            AttributeType::Text    => $value->getValueText(),
            AttributeType::Image   => $value->getValueImageUrl(),
            AttributeType::Numeric => $value->getValueNumber(),
            AttributeType::Date    => $value->getValueDate()?->format('Y-m-d'),
            AttributeType::Boolean => $value->getValueBool(),
            AttributeType::Select  => $value->getValueOption(),
            AttributeType::Period  => [
                'from' => $value->getValueDate()?->format('Y-m-d'),
                'to'   => $value->getValueDateEnd()?->format('Y-m-d'),
            ],
        };
    }
}
