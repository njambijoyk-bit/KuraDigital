<?php

namespace App\Services;

class SentimentAnalysisService
{
    private const POSITIVE_WORDS = [
        'good', 'great', 'excellent', 'strong', 'popular', 'successful', 'win', 'winning',
        'support', 'supporters', 'endorsed', 'endorsement', 'praise', 'praised', 'admired',
        'respected', 'impressive', 'effective', 'progress', 'achievement', 'achieved',
        'improved', 'improvement', 'growth', 'momentum', 'advantage', 'lead', 'leading',
        'confident', 'favorable', 'favourable', 'positive', 'promising',
        'thriving', 'energized', 'enthusiastic', 'optimistic', 'credible', 'trusted',
        'honest', 'reliable', 'capable', 'competent', 'experienced', 'dedicated',
        'committed', 'loyal', 'united', 'organized', 'funded', 'well-funded',
        'charismatic', 'influential', 'powerful', 'dominant', 'victory', 'triumph',
        'applauded', 'celebrated', 'mobilized', 'rally', 'rallies', 'turnout',
        'donation', 'donations', 'fundraising', 'backing', 'alliance', 'coalition',
        'gain', 'gains', 'surge', 'rising', 'increased', 'boost', 'boosted',
        'approve', 'approval', 'popularity', 'beloved', 'champion', 'hero',
        'transparent', 'accountable', 'visionary', 'inspiring', 'transformative',
        'landslide', 'mandate', 'consensus', 'bipartisan', 'pragmatic',
        'reformer', 'progressive', 'innovative', 'strategic', 'resilient',
    ];

    private const NEGATIVE_WORDS = [
        'bad', 'poor', 'weak', 'failing', 'failed', 'scandal', 'corrupt', 'corruption',
        'controversy', 'controversial', 'accused', 'allegations', 'alleged', 'criminal',
        'arrested', 'charged', 'investigated', 'investigation', 'fraud', 'embezzlement',
        'stolen', 'theft', 'mismanagement', 'incompetent', 'incompetence', 'ineffective',
        'inefficient', 'unpopular', 'opposition', 'criticized', 'criticism', 'condemned',
        'rejected', 'defeat', 'defeated', 'loss', 'losing', 'decline', 'declining',
        'dropped', 'falling', 'fell', 'collapse', 'collapsed', 'crisis', 'problem',
        'problems', 'trouble', 'troubled', 'dangerous', 'threat', 'threatening',
        'violent', 'violence', 'unrest', 'protest', 'protests', 'boycott', 'boycotted',
        'divided', 'division', 'split', 'infighting', 'defection', 'defected',
        'abandoned', 'deserted', 'isolated', 'vulnerable', 'exposed',
        'dishonest', 'liar', 'lies', 'deception', 'misleading', 'misinformation',
        'bribe', 'bribery', 'rigged', 'rigging', 'intimidation', 'harassment',
        'abuse', 'misuse', 'wasteful', 'negligent', 'negligence', 'reckless',
        'disapprove', 'disapproval', 'distrust', 'mistrust', 'hate', 'hated',
        'anger', 'angry', 'hostile', 'hostility', 'enemy', 'enemies',
        'nepotism', 'cronyism', 'kleptocracy', 'authoritarian', 'dictatorial',
        'censorship', 'suppression', 'tyranny', 'oppression', 'repression',
        'indicted', 'convicted', 'sentenced', 'impeached', 'recalled',
        'bankrupt', 'insolvent', 'defaulted', 'stagnation', 'recession',
    ];

    /** Swahili sentiment words for the Kenyan political context. */
    private const SWAHILI_POSITIVE = [
        'mzuri', 'bora', 'imara', 'ushindi', 'maendeleo', 'umoja', 'amani',
        'haki', 'uongozi', 'mafanikio', 'nguvu', 'upendo', 'sifa', 'baraka',
        'ushirikiano', 'tumaini', 'furaha', 'ustawi', 'uadilifu', 'kazi',
    ];

    private const SWAHILI_NEGATIVE = [
        'mbaya', 'dhaifu', 'ufisadi', 'kashfa', 'hasira', 'chuki', 'uongo',
        'udhalimu', 'ukandamizaji', 'wizi', 'uhalifu', 'rushwa', 'ubaguzi',
        'maangamizi', 'migogoro', 'hatari', 'unyanyasaji', 'ukosefu', 'laana',
        'fitina', 'usaliti', 'uhaini', 'dharau', 'jeuri', 'ukatili',
    ];

    private const INTENSIFIERS = [
        'very', 'extremely', 'highly', 'incredibly', 'significantly', 'massively',
        'overwhelmingly', 'completely', 'totally', 'absolutely', 'deeply', 'strongly',
        'seriously', 'severely', 'remarkably', 'substantially',
        'sana', 'kabisa', 'kupita', // Swahili intensifiers
    ];

    private const DIMINISHERS = [
        'somewhat', 'slightly', 'marginally', 'partially', 'fairly', 'rather',
        'a bit', 'a little', 'mildly', 'moderately', 'kidogo', // 'kidogo' = Swahili 'a little'
    ];

    private const NEGATORS = [
        'not', 'no', 'never', 'neither', 'nor', 'hardly', 'barely', 'scarcely',
        'without', 'lack', 'lacking', 'absence', "don't", "doesn't", "didn't",
        "won't", "wouldn't", "couldn't", "shouldn't", "isn't", "aren't", "wasn't",
        'hapana', 'si', 'sio', 'haina', 'hamna', // Swahili negators
    ];

    /** Bigram phrases that carry sentiment as a unit. */
    private const COMPOUND_POSITIVE = [
        'well run', 'well managed', 'well organized', 'well funded',
        'grass roots', 'grassroots support', 'clean record', 'track record',
        'public trust', 'broad support', 'strong mandate', 'clear winner',
        'way forward', 'good governance', 'turning point', 'stepping up',
    ];

    private const COMPOUND_NEGATIVE = [
        'cover up', 'sold out', 'witch hunt', 'power grab', 'land grab',
        'ghost workers', 'hate speech', 'fake news', 'money laundering',
        'conflict of interest', 'abuse of power', 'ethnic tension',
        'vote buying', 'voter suppression', 'ballot stuffing',
        'slush fund', 'shell company', 'pay to play', 'quid pro quo',
    ];

    /**
     * Analyze text and return a sentiment score, label, and confidence.
     *
     * @return array{score: float, label: string, confidence: float}
     */
    public function analyze(string $text): array
    {
        $text = strtolower($text);

        // Phase 1: Score compound phrases first, then remove them so words aren't double-counted
        $compoundPositive = 0;
        $compoundNegative = 0;
        foreach (self::COMPOUND_POSITIVE as $phrase) {
            $count = substr_count($text, $phrase);
            if ($count > 0) {
                $compoundPositive += $count * 1.5;
                $text = str_replace($phrase, ' ', $text);
            }
        }
        foreach (self::COMPOUND_NEGATIVE as $phrase) {
            $count = substr_count($text, $phrase);
            if ($count > 0) {
                $compoundNegative += $count * 1.5;
                $text = str_replace($phrase, ' ', $text);
            }
        }

        $words = preg_split('/\s+/', preg_replace('/[^\w\s\'-]/', ' ', $text));
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));
        $wordCount = count($words);

        if ($wordCount === 0 && $compoundPositive === 0 && $compoundNegative === 0) {
            return ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0];
        }

        $allPositive = array_merge(self::POSITIVE_WORDS, self::SWAHILI_POSITIVE);
        $allNegative = array_merge(self::NEGATIVE_WORDS, self::SWAHILI_NEGATIVE);

        $positiveCount = $compoundPositive;
        $negativeCount = $compoundNegative;
        $matchedWords = 0;

        for ($i = 0; $i < $wordCount; $i++) {
            $word = $words[$i];
            $isPositive = in_array($word, $allPositive, true);
            $isNegative = in_array($word, $allNegative, true);

            if (!$isPositive && !$isNegative) {
                continue;
            }

            $matchedWords++;
            $multiplier = 1.0;

            // Check for intensifiers in the preceding 2 words
            for ($j = max(0, $i - 2); $j < $i; $j++) {
                if (in_array($words[$j], self::INTENSIFIERS, true)) {
                    $multiplier = 1.5;
                    break;
                }
            }

            // Check for diminishers in the preceding 2 words (reduces weight)
            if ($multiplier === 1.0) {
                for ($j = max(0, $i - 2); $j < $i; $j++) {
                    if (in_array($words[$j], self::DIMINISHERS, true)) {
                        $multiplier = 0.5;
                        break;
                    }
                }
            }

            // Check for negators in the preceding 3 words
            $negated = false;
            for ($j = max(0, $i - 3); $j < $i; $j++) {
                if (in_array($words[$j], self::NEGATORS, true)) {
                    $negated = true;
                    break;
                }
            }

            if ($negated) {
                if ($isPositive) {
                    $negativeCount += $multiplier;
                } else {
                    $positiveCount += $multiplier;
                }
            } else {
                if ($isPositive) {
                    $positiveCount += $multiplier;
                } else {
                    $negativeCount += $multiplier;
                }
            }
        }

        $total = $positiveCount + $negativeCount;
        if ($total === 0.0) {
            return ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0];
        }

        // Score ranges from -1 (fully negative) to +1 (fully positive)
        $score = round(($positiveCount - $negativeCount) / $total, 2);

        $label = 'neutral';
        if ($score >= 0.3) {
            $label = 'positive';
        } elseif ($score <= -0.3) {
            $label = 'negative';
        }

        // Confidence: how much of the text carried sentiment (0.0 to 1.0)
        $totalMatched = $matchedWords + $compoundPositive + $compoundNegative;
        $confidence = $wordCount > 0
            ? round(min(1.0, $totalMatched / ($wordCount * 0.15)), 2)
            : 0.0;

        return ['score' => $score, 'label' => $label, 'confidence' => $confidence];
    }

    /**
     * Scan text for mentions of any opponent names from a list.
     *
     * @param  string  $text
     * @param  array<int, array{id: int, name: string}>  $opponents
     * @return array<int, array{opponent_id: int, opponent_name: string, score: float, label: string}>
     */
    public function scanForOpponentMentions(string $text, array $opponents): array
    {
        $results = [];
        $lowerText = strtolower($text);

        foreach ($opponents as $opponent) {
            $name = strtolower($opponent['name']);

            // Check full name and individual name parts (first/last)
            $nameParts = preg_split('/\s+/', $name);
            $mentioned = str_contains($lowerText, $name);

            if (!$mentioned) {
                // Check if any significant name part (3+ chars) appears
                foreach ($nameParts as $part) {
                    if (strlen($part) >= 3 && str_contains($lowerText, $part)) {
                        $mentioned = true;
                        break;
                    }
                }
            }

            if ($mentioned) {
                $sentiment = $this->analyze($text);
                $results[] = [
                    'opponent_id' => $opponent['id'],
                    'opponent_name' => $opponent['name'],
                    'score' => $sentiment['score'],
                    'label' => $sentiment['label'],
                ];
            }
        }

        return $results;
    }
}
