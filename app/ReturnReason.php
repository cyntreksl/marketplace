<?php

namespace App;

enum ReturnReason: string
{
    case Damaged = 'damaged';
    case IncorrectItem = 'incorrect_item';
    case NotAsDescribed = 'not_as_described';
    case MissingParts = 'missing_parts';
    case SafetyOrAuthenticity = 'safety_or_authenticity';
    case ChangeOfMind = 'change_of_mind';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged on arrival',
            self::IncorrectItem => 'Incorrect item received',
            self::NotAsDescribed => 'Not as described',
            self::MissingParts => 'Missing parts or accessories',
            self::SafetyOrAuthenticity => 'Safety or authenticity concern',
            self::ChangeOfMind => 'Changed my mind',
            self::Other => 'Other',
        };
    }
}
