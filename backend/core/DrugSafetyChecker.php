<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Enterprise Clinical Pharmacy Safety Engine
 * 20-Year Architecture Standard: Strict medical toxicology & pharmacological interaction matrix
 * Detects dangerous drug-drug interactions, duplicate therapeutic classes, and dosage ceilings.
 */
class DrugSafetyChecker {

    /**
     * Known High-Risk Pharmacological Interaction Matrix
     */
    private static array $knownInteractions = [
        [
            'drugs'    => ['aspirin', 'ibuprofen'],
            'severity' => 'High',
            'warning'  => 'Duplicate NSAID Therapy: Concurrent use significantly increases risk of acute gastrointestinal ulceration, severe bleeding, and nephrotoxicity.',
            'action'   => 'Avoid combination. Substitute with non-NSAID analgesic (e.g. Paracetamol) if pain control is required.'
        ],
        [
            'drugs'    => ['warfarin', 'aspirin'],
            'severity' => 'Critical',
            'warning'  => 'Anticoagulant + Antiplatelet Synergism: Dramatic increase in major systemic hemorrhage risk.',
            'action'   => 'Requires explicit cardiologist authorization and INR monitoring.'
        ],
        [
            'drugs'    => ['warfarin', 'ibuprofen'],
            'severity' => 'Critical',
            'warning'  => 'NSAID + Coumarin Toxicity: Ibuprofen displaces Warfarin from protein binding and inhibits platelet COX-1.',
            'action'   => 'Contraindicated. Use topical analgesics or paracetamol under physician guidance.'
        ],
        [
            'drugs'    => ['clopidogrel', 'omeprazole'],
            'severity' => 'Moderate',
            'warning'  => 'CYP2C19 Bioavailability Inhibition: Omeprazole significantly reduces conversion of Clopidogrel to its active antiplatelet metabolite.',
            'action'   => 'Recommend substituting Omeprazole with Pantoprazole or Rabeprazole.'
        ],
        [
            'drugs'    => ['ramipril', 'spironolactone'],
            'severity' => 'High',
            'warning'  => 'Hyperkalemia Risk: Dual aldosterone suppression can lead to lethal serum potassium elevations.',
            'action'   => 'Monitor serum potassium and renal function closely.'
        ],
        [
            'drugs'    => ['metformin', 'alcohol'],
            'severity' => 'High',
            'warning'  => 'Lactic Acidosis: Alcohol potentiates Metformin effect on lactate metabolism.',
            'action'   => 'Advise patient to completely abstain from alcohol while on Biguanide therapy.'
        ],
        [
            'drugs'    => ['ciprofloxacin', 'antacid'],
            'severity' => 'Moderate',
            'warning'  => 'Chelation & Absorption Block: Multivalent cations (Al/Mg/Ca) bind fluoroquinolones, dropping bioavailability by up to 85%.',
            'action'   => 'Administer Ciprofloxacin at least 2 hours before or 6 hours after antacid intake.'
        ],
        [
            'drugs'    => ['sildenafil', 'nitroglycerin'],
            'severity' => 'Critical',
            'warning'  => 'Fatal Hypotension: Severe, refractory cyclic-GMP mediated vasodilation.',
            'action'   => 'Absolute Contraindication. Do not co-prescribe or dispense.'
        ],
        [
            'drugs'    => ['atorvastatin', 'clarithromycin'],
            'severity' => 'High',
            'warning'  => 'CYP3A4 Inhibition / Rhabdomyolysis: Clarithromycin causes massive serum accumulation of Atorvastatin, risking acute renal failure.',
            'action'   => 'Temporarily hold statin therapy during macrolide antimicrobial course.'
        ]
    ];

    /**
     * Analyze a list of medicine names / generic formulas for clinical interactions
     * 
     * @param array $medicineItems Array of associative items containing 'name' and/or 'generic_name'
     * @return array List of detected interaction alerts
     */
    public static function checkInteractions(array $medicineItems): array {
        $detectedAlerts = [];
        $combinedTexts = [];

        foreach ($medicineItems as $idx => $item) {
            $name = strtolower($item['name'] ?? '');
            $gen  = strtolower($item['generic_name'] ?? '');
            $combinedTexts[$idx] = "{$name} {$gen}";
        }

        $totalItems = count($combinedTexts);
        if ($totalItems < 2) {
            return [];
        }

        foreach (self::$knownInteractions as $rule) {
            [$drugA, $drugB] = $rule['drugs'];
            $foundA = false;
            $foundB = false;
            $nameA = '';
            $nameB = '';

            foreach ($combinedTexts as $idx => $text) {
                if (str_contains($text, $drugA)) {
                    $foundA = true;
                    $nameA = $medicineItems[$idx]['name'] ?? ucfirst($drugA);
                }
                if (str_contains($text, $drugB)) {
                    $foundB = true;
                    $nameB = $medicineItems[$idx]['name'] ?? ucfirst($drugB);
                }
            }

            if ($foundA && $foundB && $nameA !== $nameB) {
                $detectedAlerts[] = [
                    'severity'    => $rule['severity'],
                    'drug1'       => $nameA,
                    'drug2'       => $nameB,
                    'warning'     => $rule['warning'],
                    'action'      => $rule['action']
                ];
            }
        }

        return $detectedAlerts;
    }

    /**
     * Check single medicine safety guidelines (e.g. pediatric / geriatric warnings)
     */
    public static function getSafetyAdvisory(string $medicineName, string $genericName): array {
        $text = strtolower("{$medicineName} {$genericName}");
        $advisories = [];

        if (str_contains($text, 'paracetamol') || str_contains($text, 'acetaminophen')) {
            $advisories[] = [
                'type' => 'Dosage Ceiling',
                'badge' => 'badge-warning',
                'msg' => 'Max adult daily ceiling: 4,000 mg (4g) in 24 hours. Verify no other cold/flu combinations contain paracetamol.'
            ];
        }
        if (str_contains($text, 'metformin')) {
            $advisories[] = [
                'type' => 'Clinical Guidance',
                'badge' => 'badge-info',
                'msg' => 'Take with or immediately after main meals to minimize gastrointestinal discomfort.'
            ];
        }
        if (str_contains($text, 'amoxicillin') || str_contains($text, 'antibiotic')) {
            $advisories[] = [
                'type' => 'Antimicrobial Stewardship',
                'badge' => 'badge-primary',
                'msg' => 'Complete the full prescribed 5 to 7 day antibiotic course even if symptoms resolve earlier.'
            ];
        }
        if (str_contains($text, 'atorvastatin') || str_contains($text, 'rosuvastatin')) {
            $advisories[] = [
                'type' => 'Optimal Administration',
                'badge' => 'badge-info',
                'msg' => 'Best taken once daily at bedtime when hepatic cholesterol synthesis is peaked.'
            ];
        }

        return $advisories;
    }
}
