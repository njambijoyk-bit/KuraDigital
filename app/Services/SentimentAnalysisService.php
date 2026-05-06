<?php

namespace App\Services;

class SentimentAnalysisService
{
    private const POSITIVE_WORDS = [
        'good', 'great', 'excellent', 'strong', 'popular', 'successful', 'win', 'winning',
        'support', 'supporters', 'endorsed', 'endorsement', 'praise', 'praised', 'admired',
        'respected', 'impressive', 'effective', 'progress', 'achievement', 'achieved',
        'improved', 'improvement', 'growth', 'momentum', 'advantage', 'lead', 'leading',
        'confident', 'confident', 'favorable', 'favourable', 'positive', 'promising',
        'thriving', 'energized', 'enthusiastic', 'optimistic', 'credible', 'trusted',
        'honest', 'reliable', 'capable', 'competent', 'experienced', 'dedicated',
        'committed', 'loyal', 'united', 'organized', 'funded', 'well-funded',
        'charismatic', 'influential', 'powerful', 'dominant', 'victory', 'triumph',
        'applauded', 'celebrated', 'mobilized', 'rally', 'rallies', 'turnout',
        'donation', 'donations', 'fundraising', 'backing', 'alliance', 'coalition',
        'gain', 'gains', 'surge', 'rising', 'increased', 'boost', 'boosted',
        'approve', 'approval', 'popularity', 'beloved', 'champion', 'hero',
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
        'abandoned', 'deserted', 'isolated', 'vulnerable', 'exposed', 'scandal',
        'dishonest', 'liar', 'lies', 'deception', 'misleading', 'misinformation',
        'bribe', 'bribery', 'rigged', 'rigging', 'intimidation', 'harassment',
        'abuse', 'misuse', 'wasteful', 'negligent', 'negligence', 'reckless',
        'disapprove', 'disapproval', 'distrust', 'mistrust', 'hate', 'hated',
        'anger', 'angry', 'hostile', 'hostility', 'enemy', 'enemies',
    ];

    private const INTENSIFIERS = [
        'very', 'extremely', 'highly', 'incredibly', 'significantly', 'massively',
        'overwhelmingly', 'completely', 'totally', 'absolutely', 'deeply', 'strongly',
        'seriously', 'severely', 'remarkably', 'substantially',
    ];

    private const NEGATORS = [
        'not', 'no', 'never', 'neither', 'nor', 'hardly', 'barely', 'scarcely',
        'without', 'lack', 'lacking', 'absence', "don't", "doesn't", "didn't",
        "won't", "wouldn't", "couldn't", "shouldn't", "isn't", "aren't", "wasn't",
    ];

    /**
     * Analyze text and return a sentiment score and label.
     *
     * @return array{score: float, label: string}
     */
    public function analyze(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', preg_replace('/[^\w\s\'-]/', ' ', $text));
        $wordCount = count($words);

        if ($wordCount === 0) {
            return ['score' => 0.0, 'label' => 'neutral'];
        }

        $positiveCount = 0;
        $negativeCount = 0;

        for ($i = 0; $i < $wordCount; $i++) {
            $word = $words[$i];
            $isPositive = in_array($word, self::POSITIVE_WORDS, true);
            $isNegative = in_array($word, self::NEGATIVE_WORDS, true);

            if (!$isPositive && !$isNegative) {
                continue;
            }

            $multiplier = 1.0;

            // Check for intensifiers in the preceding 2 words
            for ($j = max(0, $i - 2); $j < $i; $j++) {
                if (in_array($words[$j], self::INTENSIFIERS, true)) {
                    $multiplier = 1.5;
                    break;
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
                // Flip the sentiment
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
            return ['score' => 0.0, 'label' => 'neutral'];
        }

        // Score ranges from -1 (fully negative) to +1 (fully positive)
        $score = round(($positiveCount - $negativeCount) / $total, 2);

        $label = 'neutral';
        if ($score >= 0.3) {
            $label = 'positive';
        } elseif ($score <= -0.3) {
            $label = 'negative';
        }

        return ['score' => $score, 'label' => $label];
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
