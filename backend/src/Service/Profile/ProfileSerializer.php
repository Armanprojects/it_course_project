<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\Attribute;
use App\Entity\AttributeValue;
use App\Entity\Cv;
use App\Entity\Profile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Enum\AttributeType;

/**
 * The single definition of what a profile looks like over the wire.
 *
 * Values are emitted under one `value` key whatever the type, so the client
 * has one shape to render and one shape to send back — the type field tells it
 * which editor to use.
 */
final readonly class ProfileSerializer
{
    /**
     * @param list<Attribute> $systemAttributes built-ins that must always show
     *
     * @return array<string, mixed>
     */
    public function serialize(Profile $profile, array $systemAttributes): array
    {
        return [
            'id'        => $profile->getId(),
            'version'   => $profile->getVersion(),
            'updatedAt' => $profile->getUpdatedAt()->format(\DATE_ATOM),
            'user'      => [
                'id'    => $profile->getUser()->getId(),
                'email' => $profile->getUser()->getEmail(),
                'roles' => $profile->getUser()->getRoles(),
            ],
            'me'       => $this->serializeMe($profile, $systemAttributes),
            'info'     => $this->serializeInfo($profile),
            'projects' => $this->serializeProjects($profile),
            'cvs'      => $this->serializeCvs($profile),
        ];
    }

    /**
     * The "Me" section: every built-in attribute, whether or not the profile
     * has a value yet. They are permanent, so an empty one still gets a row —
     * otherwise the section would silently shrink for a new user.
     *
     * @param list<Attribute> $systemAttributes
     *
     * @return list<array<string, mixed>>
     */
    private function serializeMe(Profile $profile, array $systemAttributes): array
    {
        $rows = [];

        foreach ($systemAttributes as $attribute) {
            $value = $profile->getValueFor($attribute);

            $rows[] = $this->describe($attribute, $value);
        }

        return $rows;
    }

    /**
     * The "Info" section: attributes the user picked from the library.
     *
     * @return list<array<string, mixed>>
     */
    private function serializeInfo(Profile $profile): array
    {
        $rows = [];

        foreach ($profile->getAttributeValues() as $value) {
            if ($value->getAttribute()->isSystem()) {
                continue;
            }

            $rows[] = $this->describe($value->getAttribute(), $value);
        }

        // Stable, readable order; the collection itself has none.
        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Attribute $attribute, ?AttributeValue $value): array
    {
        return [
            'attributeId' => $attribute->getId(),
            'name'        => $attribute->getName(),
            'description' => $attribute->getDescription(),
            'category'    => $attribute->getCategory()->value,
            'type'        => $attribute->getType()->value,
            'options'     => $attribute->getOptions(),
            'system'      => $attribute->isSystem(),
            'value'       => null === $value ? null : $this->readValue($value),
            'empty'       => null === $value || $value->isEmpty(),
            'version'     => $value?->getVersion(),
        ];
    }

    /**
     * @return mixed scalar, or {from,to} for a period
     */
    private function readValue(AttributeValue $value): mixed
    {
        if ($value->isEmpty()) {
            return null;
        }

        return match ($value->getType()) {
            AttributeType::String  => $value->getValueString(),
            AttributeType::Text    => $value->getValueText(),
            AttributeType::Image   => $value->getValueImageUrl(),
            // Kept as a string: a decimal(20,6) does not survive a float
            // round-trip, and the client only ever displays or echoes it back.
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

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeProjects(Profile $profile): array
    {
        $projects = [];

        foreach ($profile->getProjects() as $project) {
            $projects[] = $this->serializeProject($project);
        }

        return $projects;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeProject(Project $project): array
    {
        $tags = [];

        foreach ($project->getTags() as $tag) {
            $tags[] = $this->serializeTag($tag);
        }

        usort($tags, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'id'          => $project->getId(),
            'name'        => $project->getName(),
            'description' => $project->getDescription(),
            'periodFrom'  => $project->getPeriodFrom()?->format('Y-m-d'),
            'periodTo'    => $project->getPeriodTo()?->format('Y-m-d'),
            'ongoing'     => $project->isOngoing(),
            'sortOrder'   => $project->getSortOrder(),
            'tags'        => $tags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTag(Tag $tag): array
    {
        return ['id' => $tag->getId(), 'name' => $tag->getName()];
    }

    /**
     * The "CVs" section: one row per CV, each linking to its position.
     *
     * @return list<array<string, mixed>>
     */
    private function serializeCvs(Profile $profile): array
    {
        $rows = [];

        foreach ($profile->getCvs() as $cv) {
            $rows[] = $this->serializeCv($cv);
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($b['updatedAt'], $a['updatedAt']));

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCv(Cv $cv): array
    {
        $position = $cv->getPosition();

        return [
            'id'            => $cv->getId(),
            'status'        => $cv->getStatus()->value,
            'likesCount'    => $cv->getLikesCount(),
            'createdAt'     => $cv->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt'     => $cv->getUpdatedAt()->format(\DATE_ATOM),
            'publishedAt'   => $cv->getPublishedAt()?->format(\DATE_ATOM),
            'position'      => [
                'id'      => $position->getId(),
                'title'   => $position->getTitle(),
                'company' => $position->getCompany(),
                'level'   => $position->getLevel(),
            ],
        ];
    }
}
