<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchPrediction extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'croatia_goals',
        'england_goals',
        'first_goal_minute',
        'yellow_cards_total',
        'ip_address',
        'user_agent',
        'accepted_rules',
        'accepted_privacy',
        'newsletter_consent',
        'winner_score',
        'is_winner',
        'contacted_at',
    ];

    protected $casts = [
        'croatia_goals' => 'integer',
        'england_goals' => 'integer',
        'first_goal_minute' => 'integer',
        'yellow_cards_total' => 'integer',
        'accepted_rules' => 'boolean',
        'accepted_privacy' => 'boolean',
        'newsletter_consent' => 'boolean',
        'winner_score' => 'integer',
        'is_winner' => 'boolean',
        'contacted_at' => 'datetime',
    ];
}
