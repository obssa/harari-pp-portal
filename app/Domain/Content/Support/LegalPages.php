<?php

namespace App\Domain\Content\Support;

/**
 * The privacy policy, terms of use and accessibility statement (PLAN.md §11,
 * ADR-0043) as first drafts in English, Amharic and Afaan Oromo, written to
 * match what the portal actually does. Each opens with a notice that it
 * awaits the branch office's legal review; Harari is left to its translator.
 * `portal:legal-pages` creates them once; after that they are ordinary
 * pages that editors change in the admin.
 */
final class LegalPages
{
    public const SLUGS = ['privacy', 'terms', 'accessibility'];

    /**
     * @return array<string, array<string, array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}>> slug => locale => page
     */
    public static function all(): array
    {
        return [
            'privacy' => [
                'en' => self::privacyEn(),
                'am' => LegalPagesAm::privacy(),
                'om' => LegalPagesOm::privacy(),
            ],
            'terms' => [
                'en' => self::termsEn(),
                'am' => LegalPagesAm::terms(),
                'om' => LegalPagesOm::terms(),
            ],
            'accessibility' => [
                'en' => self::accessibilityEn(),
                'am' => LegalPagesAm::accessibility(),
                'om' => LegalPagesOm::accessibility(),
            ],
        ];
    }

    /**
     * A TipTap document: the draft notice, then a heading and paragraphs per section.
     *
     * @param  array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}  $page
     * @return array<string, mixed>
     */
    public static function body(array $page): array
    {
        $content = [['type' => 'customBlock', 'attrs' => ['id' => 'callout', 'config' => ['tone' => 'warning', 'text' => $page['draft']]]]];
        foreach ($page['sections'] as [$heading, $paragraphs]) {
            $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => $heading]]];
            foreach ($paragraphs as $paragraph) {
                $content[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]];
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }

    private const DRAFT_EN = 'Draft for legal review: this text has not yet been approved by the branch office and may change.';

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    private static function privacyEn(): array
    {
        return [
            'title' => 'Privacy policy',
            'excerpt' => 'What this website collects about you, why, for how long, and how to ask for a copy or for deletion.',
            'draft' => self::DRAFT_EN,
            'sections' => [
                ['Who is responsible', [
                    'This website is run by the Prosperity Party Harari Regional Branch Office ("the branch office"). You can reach us through the contact page or the email address at the foot of every page.',
                ]],
                ['What we collect and why', [
                    'Event registrations: your name and your phone number or email address, to manage your place, send your ticket and remind you of the event. They are deleted 180 days after the event.',
                    'Messages and requests: your name, your contact details, your answers and any files you attach, so that we can answer you. They are deleted two years after the matter is closed.',
                    'Visits: we count how often pages are opened without cookies and without anything that identifies you: only the page, its language, the site you came from, the kind of device and the country. Browsers that ask not to be tracked are not counted. We use no advertising or tracking services.',
                    'Security: our hosting provider and Cloudflare process technical data such as IP addresses to deliver the site and protect it from attacks. Forms may use Cloudflare Turnstile to tell people from automated programs.',
                    'Videos and maps come from YouTube (in its privacy-enhanced mode) and OpenStreetMap only when you choose to play or show them.',
                ]],
                ['Our legal basis', [
                    'We use your data with your consent, which you give on each form, and to carry out the branch office\'s task of informing and serving the public, in line with the Personal Data Protection Proclamation No. 1321/2024.',
                ]],
                ['Who can see your data', [
                    'Only staff of the branch office and its offices whose role needs it, for example the office that handles your request. We do not sell your data or use it for advertising. Our hosting, email and text message providers process it only on our behalf.',
                ]],
                ['How we protect it', [
                    'Names and contact details are stored encrypted, staff accounts use two-step sign-in, every access is recorded, and backups are encrypted and kept for 30 days.',
                ]],
                ['Your rights', [
                    'You may ask what we hold about you, ask us to correct it, ask us to delete it, and withdraw your consent at any time. Write to us through the contact page with the email address or phone number you used. We will answer as soon as we can and within the time the law sets.',
                ]],
                ['Cookies', [
                    'The site remembers the language you chose in a small cookie. Pages with a form use a session cookie while you fill it in. We set no cookies for advertising or tracking.',
                ]],
                ['Changes', [
                    'When this policy changes, the new text is published on this page with its date.',
                ]],
            ],
        ];
    }

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    private static function termsEn(): array
    {
        return [
            'title' => 'Terms of use',
            'excerpt' => 'The rules for using this website and its content.',
            'draft' => self::DRAFT_EN,
            'sections' => [
                ['About this website', [
                    'This is the website of the Prosperity Party Harari Regional Branch Office. By using it you accept these terms.',
                ]],
                ['Using our content', [
                    'You may share and quote our news, statements and documents if you name the branch office as the source, link to the original and do not change its meaning. Some photos and documents belong to others; their notes say so.',
                ]],
                ['Accuracy', [
                    'We work to keep the information correct and up to date. Where a document on this site differs from the signed original, the original prevails.',
                ]],
                ['Your messages', [
                    'Please write respectfully. We may decline to answer or publish messages that are abusive, unlawful or that share other people\'s personal details.',
                ]],
                ['Other websites', [
                    'Links to other websites are given for your convenience; we are not responsible for their content.',
                ]],
                ['Availability', [
                    'We may change, interrupt or stop parts of the site, for example for maintenance.',
                ]],
                ['Applicable law', [
                    'These terms are governed by the laws of the Federal Democratic Republic of Ethiopia and of the Harari Regional State.',
                ]],
                ['Contact', [
                    'Questions about these terms are welcome through the contact page.',
                ]],
            ],
        ];
    }

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    private static function accessibilityEn(): array
    {
        return [
            'title' => 'Accessibility statement',
            'excerpt' => 'How we make this website usable for everyone, what is not yet right, and how to tell us.',
            'draft' => self::DRAFT_EN,
            'sections' => [
                ['Our aim', [
                    'We want everyone to be able to use this website, whatever their abilities, device or language. We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA.',
                ]],
                ['What we do', [
                    'Every change is checked automatically for accessibility problems in a real browser before it goes live. Pages work with the keyboard alone, have a link to skip to the content, and keep a visible focus.',
                    'Text can be enlarged to twice its size, colours have enough contrast, and movement is reduced when your device asks for it. Ethiopic text is marked with its language so that screen readers read it correctly.',
                    'The site is available in English, Amharic and Afaan Oromo, with Harari being translated.',
                ]],
                ['What is not yet right', [
                    'Some older documents, especially scanned PDF files, cannot be read by screen readers. Some videos have no captions yet. Parts of the Harari version still show Amharic or English text.',
                ]],
                ['Tell us', [
                    'If something on this site is hard to use, or you need a document in another form, write to us through the contact page. We aim to answer within five working days.',
                ]],
                ['About this statement', [
                    'This statement will be reviewed when the site changes and at least once a year.',
                ]],
            ],
        ];
    }
}
