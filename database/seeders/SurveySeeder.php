<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Seeder;

class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::first();
        if (!$campaign) {
            return;
        }

        $admin = User::first();
        if (!$admin) {
            return;
        }

        $survey = Survey::create([
            'campaign_id' => $campaign->id,
            'created_by' => $admin->id,
            'title' => 'Community Priorities Survey 2026',
            'description' => 'Help us understand the most pressing issues in your ward. Your responses will directly shape our campaign manifesto and resource allocation.',
            'status' => 'active',
            'ward' => null,
            'constituency' => 'Starehe',
            'county' => 'Nairobi',
            'starts_at' => now()->subDays(14),
            'ends_at' => now()->addDays(30),
            'questions' => [
                ['id' => 'q1', 'type' => 'select', 'text' => 'What is the most important issue in your community?', 'options' => ['Roads & Infrastructure', 'Water & Sanitation', 'Security', 'Healthcare', 'Education', 'Employment'], 'required' => true],
                ['id' => 'q2', 'type' => 'number', 'text' => 'On a scale of 1-10, how would you rate current service delivery?', 'options' => [], 'required' => true],
                ['id' => 'q3', 'type' => 'boolean', 'text' => 'Would you support a community development levy for local projects?', 'options' => [], 'required' => true],
                ['id' => 'q4', 'type' => 'multiselect', 'text' => 'Which services need the most improvement?', 'options' => ['Street lighting', 'Garbage collection', 'Public toilets', 'Drainage', 'Market facilities', 'Public parks'], 'required' => false],
                ['id' => 'q5', 'type' => 'text', 'text' => 'What specific project would you like to see in your ward?', 'options' => [], 'required' => false],
            ],
        ]);

        $wards = ['Pangani', 'Pumwani', 'Huruma', 'Ngara', 'Kariokor'];
        $priorities = ['Roads & Infrastructure', 'Water & Sanitation', 'Security', 'Healthcare', 'Education', 'Employment'];
        $services = ['Street lighting', 'Garbage collection', 'Public toilets', 'Drainage', 'Market facilities', 'Public parks'];
        $projects = [
            'Build a footbridge over Nairobi River at Grogan',
            'Install CCTV cameras in Huruma estate',
            'Renovate Pumwani Maternity Hospital',
            'Build a modern market at Kariokor',
            'Fix potholes on Juja Road',
            'Create a youth empowerment centre',
            'Upgrade Pangani water supply',
            'Build a community library in Ngara',
        ];

        for ($i = 0; $i < 25; $i++) {
            $ward = $wards[array_rand($wards)];
            $selectedServices = array_slice(
                $services,
                rand(0, 3),
                rand(1, 3)
            );

            SurveyResponse::create([
                'survey_id' => $survey->id,
                'submitted_by' => $i < 5 ? $admin->id : null,
                'ward' => $ward,
                'constituency' => 'Starehe',
                'county' => 'Nairobi',
                'answers' => [
                    ['question_id' => 'q1', 'value' => $priorities[array_rand($priorities)]],
                    ['question_id' => 'q2', 'value' => rand(2, 9)],
                    ['question_id' => 'q3', 'value' => rand(0, 1) === 1],
                    ['question_id' => 'q4', 'value' => array_values($selectedServices)],
                    ['question_id' => 'q5', 'value' => $projects[array_rand($projects)]],
                ],
            ]);
        }

        // Second survey (draft)
        Survey::create([
            'campaign_id' => $campaign->id,
            'created_by' => $admin->id,
            'title' => 'Youth Employment Needs Assessment',
            'description' => 'Understanding the skills gap and employment challenges facing young people in Starehe constituency.',
            'status' => 'draft',
            'constituency' => 'Starehe',
            'county' => 'Nairobi',
            'questions' => [
                ['id' => 'q1', 'type' => 'select', 'text' => 'What is your age bracket?', 'options' => ['18-24', '25-30', '31-35'], 'required' => true],
                ['id' => 'q2', 'type' => 'select', 'text' => 'What is your highest level of education?', 'options' => ['Primary', 'Secondary', 'Certificate', 'Diploma', 'Degree', 'Postgraduate'], 'required' => true],
                ['id' => 'q3', 'type' => 'boolean', 'text' => 'Are you currently employed?', 'options' => [], 'required' => true],
                ['id' => 'q4', 'type' => 'multiselect', 'text' => 'What skills training would you like?', 'options' => ['ICT/Digital', 'Plumbing', 'Electrical', 'Tailoring', 'Catering', 'Mechanics', 'Business Management'], 'required' => false],
            ],
        ]);
    }
}
