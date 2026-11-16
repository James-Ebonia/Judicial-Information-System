<?php
// Lawyer types grouped by field. Edit this list to add or remove types.
$lawyer = [
    'Litigation and Dispute Resolution' => [
        'Civil Litigator',
        'Criminal Defense Lawyer',
        'Appellate Lawyer',
        'ADR / Arbitration Lawyer',
        'Personal Injury / Tort Lawyer',
        'Medical Malpractice Lawyer',
        'Class Action / Mass Tort Lawyer',
    ],
    'Business, Corporate and Finance' => [
        'Corporate / Commercial Lawyer',
        'Mergers and Acquisitions Lawyer',
        'Securities and Capital Markets Lawyer',
        'Banking and Finance Lawyer',
        'Tax Lawyer',
        'Bankruptcy / Insolvency Lawyer',
        'Antitrust / Competition Lawyer',
        'Insurance Lawyer',
    ],
    'Personal, Family and Property' => [
        'Family Lawyer',
        'Estate Planning and Probate Lawyer',
        'Real Estate and Land Registration Lawyer',
        'Construction Lawyer',
        'Immigration Lawyer',
        'Consumer Protection Lawyer',
    ],
    'Workplace and Public Interest' => [
        'Labor and Employment Lawyer',
        'Human Rights and Civil Liberties Lawyer',
        'Constitutional / Public Interest Lawyer',
        'Election Lawyer',
        'Environmental Lawyer',
    ],
    'Specialized Industries and Emerging Fields' => [
        'Intellectual Property Lawyer',
        'Cyber, Tech and Data Privacy Lawyer',
        'Entertainment, Media and Sports Lawyer',
        'Healthcare and Pharmaceutical Lawyer',
        'Energy and Utilities Lawyer',
        'Maritime and Admiralty Lawyer',
        'Aviation Lawyer',
        'Education Lawyer',
        'Military / JAG Lawyer',
    ],
    'Institutional and Functional Roles' => [
        'Public Attorney / Public Defender',
        'State Counsel / Solicitor',
        'In-House / Corporate Counsel',
        'Notary Public',
        'Legislative Lawyer / Policy Drafter',
        'Compliance Officer / Risk Counsel',
        'Law Professor / Legal Academic',
    ],
];

$groups = [
    'Court and Law Enforcement' => [
        'judge'        => 'Judge',
        'clerk'        => 'Clerk of Court',
        'prosecutor'   => 'Prosecutor',
        'investigator' => 'Investigator',
        'admin'        => 'Administrator',
    ],
];

foreach ($lawyer as $group => $names) {
    foreach ($names as $name) {
        $groups[$group]['lawyer|' . $name] = $name;
    }
}

return ['groups' => $groups, 'lawyer' => $lawyer];