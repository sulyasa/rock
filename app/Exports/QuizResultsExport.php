<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GameSession;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;

class QuizResultsExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private GameSession $session
    ) {}

    /**
     * Get the data collection to export.
     */
    public function collection(): Collection
    {
        return DB::table('player_answers')
            ->join('users', 'player_answers.user_id', '=', 'users.id')
            ->where('player_answers.game_session_id', $this->session->id)
            ->select(
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw('SUM(player_answers.points) as total_points'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_answers_count'),
                DB::raw('COUNT(player_answers.id) as total_answers_count')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_points')
            ->get();
    }

    /**
     * Map each row of the collection.
     *
     * @param mixed $row
     */
    public function map($row): array
    {
        return [
            $row->user_id,
            $row->name,
            $row->email,
            $row->total_points,
            $row->correct_answers_count,
            $row->total_answers_count,
            $row->total_answers_count > 0 
                ? round(($row->correct_answers_count / $row->total_answers_count) * 100, 2) . '%'
                : '0%',
        ];
    }

    /**
     * Get table headings.
     */
    public function headings(): array
    {
        return [
            'ID Игрока',
            'Имя',
            'Email',
            'Всего очков',
            'Правильных ответов',
            'Всего ответов',
            'Процент правильных',
        ];
    }

    /**
     * Get worksheet title.
     */
    public function title(): string
    {
        return 'Итоги Викторины #' . $this->session->pin;
    }
}
