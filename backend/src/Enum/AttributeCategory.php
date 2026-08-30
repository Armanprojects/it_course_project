<?php

declare(strict_types=1);

namespace App\Enum;


enum AttributeCategory: string
{
    case Certification      = 'certification';
    case DomainKnowledge    = 'domain_knowledge';
    case PersonalInformation = 'personal_information';
    case SoftSkills         = 'soft_skills';
}
