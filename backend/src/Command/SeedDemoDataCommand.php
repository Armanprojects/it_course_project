<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\Profile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fills an empty database with a believable catalogue: attributes, tags,
 * recruiters, candidates with filled-in profiles, positions and submitted CVs.
 *
 * Written as a console command rather than a fixtures bundle so it also runs
 * against the deployed database, where dev dependencies are not installed.
 *
 *   php bin/console app:seed          # skips if data already exists
 *   php bin/console app:seed --fresh  # wipes the demo data first
 *
 * Every account it creates shares the same password, printed at the end.
 */
#[AsCommand(
    name: 'app:seed',
    description: 'Наполнить базу демонстрационными позициями, атрибутами и резюме',
)]
final class SeedDemoDataCommand extends Command
{
    private const PASSWORD = 'Password123!';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Удалить существующие данные перед наполнением',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('fresh')) {
            $this->truncate($io);
        } elseif ($this->alreadySeeded()) {
            $io->warning('В базе уже есть позиции. Запустите с --fresh, чтобы пересоздать данные.');

            return Command::SUCCESS;
        }

        $io->title('Наполнение демонстрационными данными');

        $tags       = $this->createTags();
        $attributes = $this->createAttributes();
        $recruiters = $this->createRecruiters();
        $this->em->flush();

        $candidates = $this->createCandidates($attributes, $tags);
        $this->em->flush();

        $positions = $this->createPositions($attributes, $tags, $recruiters);
        $this->em->flush();

        $cvCount = $this->createCvs($positions, $candidates);
        $this->em->flush();

        $io->success(sprintf(
            '%d позиций, %d атрибутов, %d тегов, %d кандидатов, %d резюме.',
            \count($positions),
            \count($attributes),
            \count($tags),
            \count($candidates),
            $cvCount,
        ));

        $io->section('Учётные записи');
        $io->text('Пароль у всех: ' . self::PASSWORD);
        $io->listing([
            'admin@example.com — администратор',
            'recruiter@example.com — рекрутер',
            'candidate@example.com — кандидат',
        ]);

        return Command::SUCCESS;
    }

    private function alreadySeeded(): bool
    {
        return (int) $this->em->createQuery('SELECT COUNT(p.id) FROM ' . Position::class . ' p')
            ->getSingleScalarResult() > 0;
    }

    /**
     * Order matters: the tables referencing others go first, and the whole set
     * is truncated rather than deleted row by row so the identity sequences
     * restart and seeded ids stay predictable between runs.
     */
    private function truncate(SymfonyStyle $io): void
    {
        $io->text('Очистка существующих данных…');

        $tables = [
            'cv_like', 'cv', 'discussion_post', 'position_access_rule',
            'position_attribute', 'position_tag', '"position"',
            'attribute_value', 'project_tag', 'project', 'attribute', 'tag',
            'email_verification_token', 'user_identity', 'profile', '"user"',
        ];

        $connection = $this->em->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE ' . implode(', ', $tables) . ' RESTART IDENTITY CASCADE',
        );
    }

    /**
     * @return array<string, Tag>
     */
    private function createTags(): array
    {
        $names = [
            'PHP', 'Symfony', 'React', 'TypeScript', 'PostgreSQL', 'Docker',
            'Kubernetes', 'Python', 'Django', 'Go', 'Java', 'Spring',
            'AWS', 'Terraform', 'GraphQL', 'Redis', 'Kafka', 'Machine Learning',
            'Figma', 'Playwright',
        ];

        $tags = [];

        foreach ($names as $name) {
            $tag = new Tag($name);
            $this->em->persist($tag);
            $tags[$name] = $tag;
        }

        return $tags;
    }

    /**
     * The library every position and profile draws from. The "personal
     * information" ones are marked as system: they are the built-ins the brief
     * says recruiters must never be able to delete.
     *
     * @return array<string, Attribute>
     */
    private function createAttributes(): array
    {
        $definitions = [
            ['First name', AttributeCategory::PersonalInformation, AttributeType::String, true, null],
            ['Last name', AttributeCategory::PersonalInformation, AttributeType::String, true, null],
            ['Location', AttributeCategory::PersonalInformation, AttributeType::String, true, null],
            ['Photo', AttributeCategory::PersonalInformation, AttributeType::Image, true, null],
            ['Summary', AttributeCategory::PersonalInformation, AttributeType::Text, true, null],
            ['English level', AttributeCategory::SoftSkills, AttributeType::Select, false, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']],
            ['IELTS score', AttributeCategory::Certification, AttributeType::Numeric, false, null],
            ['GPA', AttributeCategory::DomainKnowledge, AttributeType::Numeric, false, null],
            ['Years of experience', AttributeCategory::DomainKnowledge, AttributeType::Numeric, false, null],
            ['Open to remote work', AttributeCategory::SoftSkills, AttributeType::Boolean, false, null],
            ['Willing to relocate', AttributeCategory::SoftSkills, AttributeType::Boolean, false, null],
            ['Presentation skills', AttributeCategory::SoftSkills, AttributeType::Select, false, ['Basic', 'Intermediate', 'Advanced']],
            ['AWS certification', AttributeCategory::Certification, AttributeType::Boolean, false, null],
            ['Available from', AttributeCategory::PersonalInformation, AttributeType::Date, false, null],
            ['Last employment', AttributeCategory::DomainKnowledge, AttributeType::Period, false, null],
            ['Preferred stack', AttributeCategory::DomainKnowledge, AttributeType::Select, false, ['Backend', 'Frontend', 'Fullstack', 'DevOps', 'Data']],
        ];

        $attributes = [];

        foreach ($definitions as [$name, $category, $type, $system, $options]) {
            $attribute = new Attribute($name, $category, $type);

            if (null !== $options) {
                $attribute->setOptions($options);
            }

            if ($system) {
                $attribute->markAsSystem();
            }

            $this->em->persist($attribute);
            $attributes[$name] = $attribute;
        }

        return $attributes;
    }

    /**
     * @return list<User>
     */
    private function createRecruiters(): array
    {
        $admin = $this->createUser('admin@example.com', UserRole::Admin);
        $admin->grantRole(UserRole::Recruiter);

        return [
            $admin,
            $this->createUser('recruiter@example.com', UserRole::Recruiter),
            $this->createUser('anna.recruiter@example.com', UserRole::Recruiter),
        ];
    }

    /**
     * Candidates with their attribute values already filled in, so the access
     * rules on the positions actually select somebody.
     *
     * @param array<string, Attribute> $attributes
     * @param array<string, Tag>       $tags
     *
     * @return list<Profile>
     */
    private function createCandidates(array $attributes, array $tags): array
    {
        $people = [
            ['candidate@example.com', 'John', 'Smith', 'Berlin, Germany', 'C1', 7.5, 3.8, 6, true, 'Advanced', 'Backend', ['PHP', 'Symfony', 'PostgreSQL', 'Docker']],
            ['maria.lopez@example.com', 'Maria', 'Lopez', 'Madrid, Spain', 'B2', 6.5, 3.5, 4, true, 'Intermediate', 'Frontend', ['React', 'TypeScript', 'Figma']],
            ['liam.chen@example.com', 'Liam', 'Chen', 'Singapore', 'C2', 8.0, 3.9, 9, false, 'Advanced', 'DevOps', ['Kubernetes', 'Docker', 'AWS', 'Terraform']],
            ['nora.hassan@example.com', 'Nora', 'Hassan', 'Cairo, Egypt', 'B2', 7.0, 3.2, 3, true, 'Basic', 'Data', ['Python', 'Machine Learning']],
            ['pavel.novak@example.com', 'Pavel', 'Novak', 'Prague, Czechia', 'B1', 5.5, 3.0, 2, true, 'Intermediate', 'Fullstack', ['Go', 'React', 'Redis']],
            ['sara.kim@example.com', 'Sara', 'Kim', 'Seoul, South Korea', 'C1', 7.5, 3.7, 7, false, 'Advanced', 'Backend', ['Java', 'Spring', 'Kafka']],
            ['omar.farouk@example.com', 'Omar', 'Farouk', 'Dubai, UAE', 'B2', 6.0, 3.4, 5, true, 'Intermediate', 'Fullstack', ['PHP', 'React', 'GraphQL']],
            ['elena.petrova@example.com', 'Elena', 'Petrova', 'Warsaw, Poland', 'C1', 8.5, 3.95, 8, true, 'Advanced', 'Data', ['Python', 'Django', 'PostgreSQL']],
        ];

        $profiles = [];

        foreach ($people as $index => [$email, $first, $last, $location, $english, $ielts, $gpa, $years, $remote, $presentation, $stack, $projectTags]) {
            $user    = $this->createUser($email, UserRole::Candidate);
            $profile = new Profile($user);
            $this->em->persist($profile);

            $this->setString($profile, $attributes['First name'], $first);
            $this->setString($profile, $attributes['Last name'], $last);
            $this->setString($profile, $attributes['Location'], $location);
            $profile->addAttribute($attributes['Summary'])
                ->setValueText(sprintf('%s %s — %s engineer with %d years of experience.', $first, $last, $stack, $years));

            $profile->addAttribute($attributes['English level'])->setValueOption($english);
            $profile->addAttribute($attributes['IELTS score'])->setValueNumber($ielts);
            $profile->addAttribute($attributes['GPA'])->setValueNumber($gpa);
            $profile->addAttribute($attributes['Years of experience'])->setValueNumber($years);
            $profile->addAttribute($attributes['Open to remote work'])->setValueBool($remote);
            $profile->addAttribute($attributes['Presentation skills'])->setValueOption($presentation);
            $profile->addAttribute($attributes['Preferred stack'])->setValueOption($stack);
            $profile->addAttribute($attributes['AWS certification'])->setValueBool(0 === $index % 3);

            $this->createProjects($profile, $tags, $projectTags, $first);

            $profiles[] = $profile;
        }

        return $profiles;
    }

    /**
     * @param array<string, Tag> $tags
     * @param list<string>       $tagNames
     */
    private function createProjects(Profile $profile, array $tags, array $tagNames, string $owner): void
    {
        $blueprints = [
            ['Internal analytics platform', 'Data pipeline and dashboards used across the company.'],
            ['Customer portal redesign', 'Rebuilt the self-service portal, cutting support tickets by a third.'],
            ['Payments integration', 'Integrated a third-party payment provider with full reconciliation.'],
        ];

        foreach ($blueprints as $order => [$name, $description]) {
            $project = new Project($profile, $name);
            $project->setDescription(sprintf('**%s.** %s', $owner, $description));
            $project->setSortOrder($order);
            $project->setPeriod(
                new \DateTimeImmutable(sprintf('-%d years', $order + 2)),
                $order > 0 ? new \DateTimeImmutable(sprintf('-%d years', $order)) : null,
            );

            foreach ($tagNames as $tagName) {
                $tag = $tags[$tagName] ?? null;

                // The counter is the caller's job by design — it is what the
                // tag cloud sorts on, so a missed increment hides the tag.
                if (null !== $tag && $project->addTag($tag)) {
                    $tag->incrementUsage();
                }
            }

            $profile->addProject($project);
            $this->em->persist($project);
        }
    }

    /**
     * @param array<string, Attribute> $attributes
     * @param array<string, Tag>       $tags
     * @param list<User>               $recruiters
     *
     * @return list<Position>
     */
    private function createPositions(array $attributes, array $tags, array $recruiters): array
    {
        $definitions = [
            [
                'Senior Backend Engineer', 'Nordwind Software', 'Senior',
                'Designing and running the services behind our logistics platform. PHP and Go, heavy on data consistency.',
                true, ['PHP', 'Symfony', 'PostgreSQL', 'Docker'],
                ['First name', 'Last name', 'Location', 'Summary', 'Years of experience', 'English level', 'Preferred stack'],
                [['Years of experience', FilterOperator::GreaterOrEqual, 5]],
            ],
            [
                'Frontend Engineer', 'Nordwind Software', 'Middle',
                'Building the customer-facing application in React and TypeScript alongside a small design team.',
                true, ['React', 'TypeScript', 'Figma'],
                ['First name', 'Last name', 'Location', 'Summary', 'English level', 'Preferred stack'],
                [],
            ],
            [
                'DevOps Engineer', 'Helio Cloud', 'Senior',
                'Owning our Kubernetes platform and the delivery pipeline behind roughly forty services.',
                false, ['Kubernetes', 'Docker', 'AWS', 'Terraform'],
                ['First name', 'Last name', 'Location', 'Years of experience', 'AWS certification'],
                [
                    ['AWS certification', FilterOperator::Equals, true],
                    ['Years of experience', FilterOperator::GreaterOrEqual, 4],
                ],
            ],
            [
                'Data Scientist', 'Helio Cloud', 'Middle',
                'Turning product telemetry into forecasting models the operations team actually uses.',
                false, ['Python', 'Machine Learning', 'PostgreSQL'],
                ['First name', 'Last name', 'Summary', 'GPA', 'English level'],
                [['GPA', FilterOperator::GreaterThan, 3.3]],
            ],
            [
                'Business Analyst', 'Aurora Consulting', 'Middle',
                'Sitting between the client and delivery, turning vague asks into specifications engineers can build.',
                true, ['Figma'],
                ['First name', 'Last name', 'Location', 'English level', 'Presentation skills', 'GPA'],
                [
                    ['English level', FilterOperator::Equals, 'C1'],
                    ['Presentation skills', FilterOperator::Equals, 'Advanced'],
                ],
            ],
            [
                'QA Automation Engineer', 'Aurora Consulting', 'Junior',
                'Growing our end-to-end suite in Playwright and keeping the pipeline honest.',
                true, ['Playwright', 'TypeScript'],
                ['First name', 'Last name', 'Location', 'Years of experience'],
                [],
            ],
            [
                'Java Backend Engineer', 'Meridian Bank', 'Senior',
                'Core banking services on Spring, where correctness matters more than throughput.',
                true, ['Java', 'Spring', 'Kafka'],
                ['First name', 'Last name', 'Location', 'Summary', 'Years of experience', 'English level'],
                [['Years of experience', FilterOperator::GreaterOrEqual, 6]],
            ],
            [
                'Fullstack Engineer', 'Meridian Bank', 'Middle',
                'Small product team, end-to-end ownership from the database to the browser.',
                true, ['Go', 'React', 'GraphQL', 'Redis'],
                ['First name', 'Last name', 'Summary', 'Preferred stack', 'Open to remote work'],
                [['Open to remote work', FilterOperator::Equals, true]],
            ],
            [
                'Engineering Manager', 'Nordwind Software', 'C-level',
                'Leading two delivery teams, accountable for both the roadmap and the people on it.',
                false, ['Kubernetes', 'PostgreSQL'],
                ['First name', 'Last name', 'Location', 'Summary', 'Years of experience', 'Presentation skills'],
                [
                    ['Years of experience', FilterOperator::GreaterOrEqual, 8],
                    ['Presentation skills', FilterOperator::Equals, 'Advanced'],
                ],
            ],
            [
                'Machine Learning Engineer', 'Helio Cloud', 'Senior',
                'Taking models from a notebook to something that survives production traffic.',
                true, ['Python', 'Machine Learning', 'AWS'],
                ['First name', 'Last name', 'Summary', 'GPA', 'Years of experience', 'English level'],
                [['GPA', FilterOperator::GreaterOrEqual, 3.5]],
            ],
            [
                'Django Developer', 'Aurora Consulting', 'Middle',
                'Maintaining and extending several long-running Django products for public sector clients.',
                true, ['Python', 'Django', 'PostgreSQL'],
                ['First name', 'Last name', 'Location', 'Years of experience', 'Preferred stack'],
                [],
            ],
            [
                'Platform Engineer', 'Meridian Bank', 'Middle',
                'Internal tooling and paved roads, so product teams ship without filing tickets.',
                true, ['Terraform', 'Docker', 'Go'],
                ['First name', 'Last name', 'Location', 'Years of experience', 'Open to remote work'],
                [['Willing to relocate', FilterOperator::IsSet, null]],
            ],
        ];

        $positions = [];

        foreach ($definitions as $index => [$title, $company, $level, $description, $isPublic, $tagNames, $attributeNames, $rules]) {
            $position = new Position($title, $recruiters[$index % \count($recruiters)]);
            $position->setCompany($company);
            $position->setLevel($level);
            $position->setShortDescription($description);
            $position->setPublic($isPublic);
            $position->setMaxProjects(2 + ($index % 3));

            foreach ($tagNames as $tagName) {
                if (isset($tags[$tagName])) {
                    $position->addProjectTag($tags[$tagName]);
                }
            }

            foreach ($attributeNames as $order => $attributeName) {
                $link = $position->addAttribute($attributes[$attributeName], $order);
                // The built-in identity fields are what makes a CV a CV, so
                // they are the ones publishing must insist on.
                $link->setRequired($attributes[$attributeName]->isSystem());
                $link->setSection($attributes[$attributeName]->getCategory()->value);
            }

            foreach ($rules as [$attributeName, $operator, $operand]) {
                $this->addAccessRule($position, $attributes[$attributeName], $operator, $operand);
            }

            // Spread the timestamps so "latest positions" has something to sort.
            $this->backdate($position, $index);

            $this->em->persist($position);
            $positions[] = $position;
        }

        return $positions;
    }

    private function addAccessRule(
        Position $position,
        Attribute $attribute,
        FilterOperator $operator,
        string|int|float|bool|null $operand,
    ): void {
        $rule = new PositionAccessRule($position, $attribute, $operator);

        match ($attribute->getType()) {
            AttributeType::Numeric => $rule->setOperandNumber($operand),
            AttributeType::Boolean => $rule->setOperandBool((bool) $operand),
            AttributeType::Select, AttributeType::String, AttributeType::Text => $rule->setOperandString(
                null === $operand ? null : (string) $operand,
            ),
            default => null,
        };

        $this->em->persist($rule);
    }

    /**
     * Timestamps are set through the mapping rather than a setter: they are
     * deliberately not writable on the entity, and a demo catalogue where every
     * row was created in the same second would make the sorting untestable.
     */
    private function backdate(Position $position, int $index): void
    {
        $metadata = $this->em->getClassMetadata(Position::class);

        $created = new \DateTimeImmutable(sprintf('-%d days', 40 - $index * 3));
        $updated = new \DateTimeImmutable(sprintf('-%d days', 20 - $index));

        $metadata->setFieldValue($position, 'createdAt', $created);
        $metadata->setFieldValue($position, 'updatedAt', $updated < $created ? $created : $updated);
    }

    /**
     * Submitted CVs, so that "most popular positions" and the counters on the
     * home page are ranking real rows rather than showing zeroes.
     *
     * @param list<Position> $positions
     * @param list<Profile>  $candidates
     */
    private function createCvs(array $positions, array $candidates): int
    {
        // A deliberately uneven spread: the first positions collect the most
        // CVs, so the top-5 table has a visible ordering.
        $spread = [8, 6, 5, 4, 4, 3, 3, 2, 2, 1, 1, 0];
        $total  = 0;

        foreach ($positions as $index => $position) {
            $wanted = $spread[$index] ?? 0;

            foreach (\array_slice($candidates, 0, $wanted) as $offset => $profile) {
                $cv = $profile->startCv($position);
                $this->em->persist($cv);
                ++$total;

                // Leave a couple of drafts: the published counter and the total
                // must be able to differ, otherwise the stats prove nothing.
                if (0 === ($offset + $index) % 4) {
                    continue;
                }

                if ($cv->isComplete()) {
                    $cv->publish();
                }
            }
        }

        return $total;
    }

    private function createUser(string $email, UserRole $role): User
    {
        $user = new User($email, $role);
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $user->verifyEmail();

        $this->em->persist($user);

        return $user;
    }

    private function setString(Profile $profile, Attribute $attribute, string $value): void
    {
        $profile->addAttribute($attribute)->setValueString($value);
    }
}
