<?php

namespace App\Enums;

enum KnowledgeBaseCategory: string
{
    case Sop = 'sop';
    case ProjectDocumentation = 'project-documentation';
    case Proposal = 'proposal';
    case TechnicalDocumentation = 'technical-documentation';
    case Reports = 'reports';
    case CompanyKnowledge = 'company-knowledge';

    public function label(): string
    {
        return match ($this) {
            self::Sop => __('SOP'),
            self::ProjectDocumentation => __('Project Documentation'),
            self::Proposal => __('Proposal'),
            self::TechnicalDocumentation => __('Technical Documentation'),
            self::Reports => __('Reports'),
            self::CompanyKnowledge => __('Company Knowledge'),
        };
    }
}
