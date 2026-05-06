<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Site;
use Illuminate\Database\Seeder;

class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::create([
            'slug' => 'demo-candidate',
            'candidate_name' => 'John Kamau',
            'position' => 'Member of Parliament',
            'constituency' => 'Starehe',
            'county' => 'Nairobi',
            'party' => 'Independent',
            'slogan' => 'Together, We Build a Better Starehe',
            'slogan_sw' => 'Pamoja, Tunajenga Starehe Bora',
            'primary_color' => '#16a34a',
            'secondary_color' => '#0f172a',
            'bio_summary' => 'A dedicated community leader with over 15 years of public service experience. Born and raised in Starehe, John understands the challenges facing our people and has a clear plan to address them.',
            'bio_summary_sw' => 'Kiongozi wa jamii aliyejitolea na uzoefu wa zaidi ya miaka 15 katika huduma za umma.',
            'bio_full' => '<p>Born and raised in Starehe Constituency, John Kamau has dedicated his life to community service and development. With a degree in Public Administration from the University of Nairobi and a Master\'s in Development Studies, he combines academic excellence with practical grassroots experience.</p><p>Over the past 15 years, John has initiated and led numerous community development projects including borehole construction, school renovation programs, and youth empowerment initiatives that have directly benefited thousands of families.</p>',
            'education' => '<ul><li>Master\'s in Development Studies — Kenyatta University</li><li>Bachelor\'s in Public Administration — University of Nairobi</li><li>Diploma in Project Management — Kenya Institute of Management</li></ul>',
            'experience' => '<ul><li>Community Development Officer (2010-2015)</li><li>Ward Development Committee Chair (2015-2020)</li><li>County Assembly Advisor (2020-Present)</li></ul>',
            'phone' => '+254712345678',
            'email' => 'info@johnkamau.co.ke',
            'office_address' => 'Kamau Campaign Office, Tom Mboya Street, Nairobi',
            'facebook_url' => 'https://facebook.com/johnkamau',
            'twitter_url' => 'https://twitter.com/johnkamau',
            'instagram_url' => 'https://instagram.com/johnkamau',
            'donation_info' => '<p><strong>M-Pesa Paybill:</strong> 123456</p><p><strong>Account Name:</strong> Kamau Campaign Fund</p>',
            'donation_goal' => 5000000.00,
            'donations_enabled' => true,
            'mpesa_paybill' => '123456',
            'pillars' => [
                ['icon' => '📚', 'title' => 'Education', 'description' => 'Quality education for every child in Starehe.'],
                ['icon' => '🏥', 'title' => 'Healthcare', 'description' => 'Accessible, affordable healthcare for all families.'],
                ['icon' => '🛣️', 'title' => 'Infrastructure', 'description' => 'Modern roads, water, and electricity for development.'],
                ['icon' => '💼', 'title' => 'Youth Employment', 'description' => 'Creating opportunities and empowering our youth.'],
            ],
            'milestones' => [
                ['year' => '2010', 'title' => 'Community Development Officer', 'description' => 'Began active community development work in Starehe.'],
                ['year' => '2015', 'title' => 'Ward Development Chair', 'description' => 'Elected to lead local development initiatives across 5 wards.'],
                ['year' => '2020', 'title' => 'County Assembly Advisor', 'description' => 'Served as senior advisor on constituency development matters.'],
                ['year' => '2027', 'title' => 'Running for MP', 'description' => 'Seeking to represent the people of Starehe at the national level.'],
            ],
        ]);

        $site->manifestoPillars()->createMany([
            ['icon' => '📚', 'title' => 'Education', 'description' => 'Investing in our children\'s future.', 'promises' => ['Build 5 new secondary schools', 'Full bursary program for needy students', 'Equip all schools with ICT labs', 'Teacher training & motivation programs'], 'sort_order' => 1],
            ['icon' => '🏥', 'title' => 'Healthcare', 'description' => 'Health for all.', 'promises' => ['Upgrade constituency hospital', 'Mobile health clinics for remote areas', 'Free maternal healthcare program', 'Mental health awareness campaigns'], 'sort_order' => 2],
            ['icon' => '🛣️', 'title' => 'Infrastructure', 'description' => 'Building the foundation for growth.', 'promises' => ['Tarmac all major ward roads', 'Street lighting for market centres', 'Clean water boreholes in every village', 'Bridge construction over major rivers'], 'sort_order' => 3],
            ['icon' => '💼', 'title' => 'Youth & Employment', 'description' => 'Empowering the next generation.', 'promises' => ['Youth enterprise fund of KES 50M', 'Skills training centres in each ward', 'Internship placement program', 'Support for SMEs and startups'], 'sort_order' => 4],
            ['icon' => '🌾', 'title' => 'Agriculture', 'description' => 'Food security and livelihoods.', 'promises' => ['Subsidised farm inputs', 'Urban farming initiatives', 'Market access for traders', 'Cold storage facilities'], 'sort_order' => 5],
            ['icon' => '🛡️', 'title' => 'Security', 'description' => 'Safe communities for all.', 'promises' => ['Community policing partnerships', 'CCTV in major centres', 'Street lighting in all estates', 'Nyumba Kumi initiative support'], 'sort_order' => 6],
        ]);

        $site->events()->createMany([
            ['title' => 'Grand Campaign Launch — Starehe', 'date' => '2027-06-15', 'time' => '10:00 AM', 'location' => 'Kamukunji Grounds', 'description' => 'Join us for a major campaign rally with music, speeches, and community engagement.'],
            ['title' => 'Youth Town Hall Forum', 'date' => '2027-06-20', 'time' => '2:00 PM', 'location' => 'Pumwani Youth Centre', 'description' => 'An open forum for youth to discuss employment, education, and opportunities.'],
            ['title' => 'Women\'s Empowerment Forum', 'date' => '2027-06-25', 'time' => '9:00 AM', 'location' => 'Pangani Community Hall', 'description' => 'Discussing women\'s issues, table banking, and economic empowerment programs.'],
            ['title' => 'Traders\' Meeting — Market Improvements', 'date' => '2027-07-01', 'time' => '11:00 AM', 'location' => 'Gikomba Market', 'description' => 'Meeting with traders to discuss market infrastructure improvements and security.'],
        ]);

        $site->newsArticles()->createMany([
            ['title' => 'Campaign Launch: A New Dawn for Starehe', 'excerpt' => 'Hundreds gathered for the official campaign launch, marking the beginning of a new chapter for community-led development.', 'date' => '2027-05-01'],
            ['title' => '100 Students Receive Full Bursaries', 'excerpt' => 'In partnership with local organisations, 100 students from disadvantaged backgrounds received full bursaries for the upcoming academic year.', 'date' => '2027-05-10'],
            ['title' => 'Infrastructure Plan: Road Improvement Begins', 'excerpt' => 'The first phase of the road improvement project has commenced, with 15km of roads set to be improved.', 'date' => '2027-05-15'],
        ]);

        $site->projects()->createMany([
            ['title' => 'Borehole Construction — Pangani', 'status' => 'completed', 'category' => 'Water', 'description' => 'Drilled and equipped a borehole serving 2,000 families.', 'impact' => '2,000 families', 'sort_order' => 1],
            ['title' => 'School Renovation — 5 Primary Schools', 'status' => 'completed', 'category' => 'Education', 'description' => 'Renovated 5 primary schools including new classrooms and latrines.', 'impact' => '1,500 students', 'sort_order' => 2],
            ['title' => 'Market Access Road — Gikomba', 'status' => 'ongoing', 'category' => 'Infrastructure', 'description' => 'Grading and gravelling of key market access roads.', 'impact' => '5,000 traders', 'sort_order' => 3],
            ['title' => 'Youth ICT Hub', 'status' => 'planned', 'category' => 'Youth', 'description' => 'Construction of a modern youth ICT hub with training facilities.', 'impact' => '500 youth annually', 'sort_order' => 4],
        ]);

        // Seed sample donations (requires a campaign linked to this site)
        $campaign = Campaign::where('site_id', $site->id)->first();
        if ($campaign) {
            $donors = [
                ['donor_name' => 'Mary Wanjiku', 'donor_phone' => '0722111222', 'amount' => 10000, 'channel' => 'mpesa', 'donated_at' => now()->subDays(12)],
                ['donor_name' => 'Peter Ochieng', 'donor_phone' => '0733222333', 'amount' => 25000, 'channel' => 'mpesa', 'donated_at' => now()->subDays(10)],
                ['donor_name' => null, 'donor_phone' => '0711333444', 'amount' => 5000, 'channel' => 'mpesa', 'is_anonymous' => true, 'donated_at' => now()->subDays(8)],
                ['donor_name' => 'Grace Akinyi', 'donor_phone' => '0700444555', 'amount' => 50000, 'channel' => 'bank_transfer', 'donated_at' => now()->subDays(6)],
                ['donor_name' => 'James Mwangi', 'donor_phone' => '0712555666', 'amount' => 15000, 'channel' => 'mpesa', 'donated_at' => now()->subDays(4)],
                ['donor_name' => 'Sarah Njeri', 'donor_phone' => '0723666777', 'amount' => 7500, 'channel' => 'mpesa', 'donated_at' => now()->subDays(2)],
                ['donor_name' => 'David Kiprop', 'donor_phone' => '0734777888', 'amount' => 100000, 'channel' => 'bank_transfer', 'donated_at' => now()->subDays(1)],
                ['donor_name' => 'Agnes Chebet', 'donor_phone' => '0745888999', 'amount' => 2000, 'channel' => 'mpesa', 'donated_at' => now()],
            ];

            foreach ($donors as $donor) {
                Donation::create(array_merge([
                    'campaign_id' => $campaign->id,
                    'currency' => 'KES',
                    'status' => 'completed',
                    'is_anonymous' => false,
                ], $donor));
            }
        }
    }
}
