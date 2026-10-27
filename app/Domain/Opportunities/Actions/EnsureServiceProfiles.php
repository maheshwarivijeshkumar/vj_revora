<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Models\ServiceProfile;
use Illuminate\Support\Collection;

/**
 * The workspace's service profiles, created on first use from what
 * bergmediadigital.com and vijeshmaheshwari.com say about each business.
 *
 * Only facts published on those sites go in; anything else (rates, the full
 * resume) is left for the owner to add on the Services page, because a drafted
 * proposal must never claim something the business did not say.
 */
final class EnsureServiceProfiles
{
    /**
     * @return Collection<int, ServiceProfile>
     */
    public function handle(): Collection
    {
        if (ServiceProfile::query()->exists()) {
            return ServiceProfile::query()->orderByDesc('is_default')->orderBy('id')->get();
        }

        ServiceProfile::create($this->agency());
        ServiceProfile::create($this->freelancer());

        return ServiceProfile::query()->orderByDesc('is_default')->orderBy('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function agency(): array
    {
        return [
            'name' => 'Berg Media Digital',
            'kind' => ServiceProfile::KIND_AGENCY,
            'tagline' => 'Software development company building digital products that move businesses forward',
            'website' => 'https://bergmediadigital.com',
            'email' => 'info@bergmediadigital.com',
            'phone' => '+44 7879 969756',
            'location' => 'Watford, United Kingdom · Dubai, United Arab Emirates',
            'about' => 'Berg Media Digital is a software development company helping businesses build, improve, and scale digital products: custom software, websites, mobile apps, AI solutions and digital platforms built around the way each business actually works. We work with businesses across the UK, UAE and other international markets, on a single service or several together. Process: Discover, Strategize, Design, Build, Launch, Grow. We reply to every brief within two working days.',
            'services' => [
                ['name' => 'Custom Software Development', 'summary' => 'CRM, ERP, SaaS, business management systems, dashboards, POS and API integrations built around how the business operates.', 'keywords' => ['custom software', 'software development', 'crm', 'erp', 'saas', 'dashboard', 'pos', 'point of sale', 'management system', 'information system', 'mis', 'api integration', 'business automation', 'inventory management', 'hrms', 'payroll', 'accounting software', 'portal development']],
                ['name' => 'Web Development', 'summary' => 'Websites, web applications and improvements to existing sites: fast, responsive and ready to grow.', 'keywords' => ['website', 'web development', 'web application', 'web app', 'web portal', 'wordpress', 'cms', 'landing page', 'redesign', 'laravel', 'php developer', 'full stack', 'full-stack', 'frontend', 'backend']],
                ['name' => 'Mobile App Development', 'summary' => 'iOS, Android and cross-platform apps, customer-facing or internal.', 'keywords' => ['mobile app', 'mobile application', 'android', 'ios app', 'flutter', 'react native', 'cross-platform']],
                ['name' => 'AI & Automation', 'summary' => 'AI assistants, chatbots, workflow automation, system integrations and custom AI applications.', 'keywords' => ['ai', 'artificial intelligence', 'chatbot', 'workflow automation', 'process automation', 'business automation', 'rpa', 'machine learning', 'llm', 'openai', 'gpt', 'zapier', 'n8n', 'whatsapp bot']],
                ['name' => 'E-commerce Development & Integrations', 'summary' => 'New online stores and improvements to existing ones: catalogue, checkout, payments, orders and integrations.', 'keywords' => ['ecommerce', 'e-commerce', 'online store', 'shopify', 'woocommerce', 'marketplace', 'payment gateway', 'checkout', 'multi-vendor']],
                ['name' => 'UI/UX Design', 'summary' => 'User flows, wireframes, high-fidelity interfaces, prototypes and design systems.', 'keywords' => ['ui/ux', 'ui ux', 'ux design', 'ui design', 'figma', 'wireframe', 'prototype', 'user interface', 'design system']],
                ['name' => 'SEO & Digital Marketing', 'summary' => 'Technical and on-page SEO, local and e-commerce SEO, content and conversion optimisation.', 'keywords' => ['seo', 'search engine optimization', 'search engine optimisation', 'digital marketing', 'social media marketing', 'google ads', 'ppc', 'content marketing', 'online marketing', 'advertising campaign']],
                ['name' => 'Cloud & DevOps', 'summary' => 'Cloud infrastructure, migration, DevOps, deployment automation and monitoring.', 'keywords' => ['cloud', 'devops', 'aws', 'azure', 'docker', 'kubernetes', 'ci/cd', 'web hosting', 'cloud migration', 'server migration']],
                ['name' => 'Branding & Creative', 'summary' => 'Brand strategy and visual identity that works across websites, products, campaigns and social media.', 'keywords' => ['branding', 'brand identity', 'logo design', 'graphic design', 'visual identity']],
            ],
            'skills' => ['laravel', 'vue', 'vue.js', 'react', 'next.js', 'typescript', 'tailwind', 'node.js', 'postgresql', 'flutter', 'aws'],
            'portfolio' => [
                ['name' => 'AVA Remarketing', 'summary' => 'Vehicle remarketing marketplace for professional UK buyers: live auctions, Buy It Now, buyer onboarding, bidding, payments and operational workflows in one platform.', 'url' => 'https://bergmediadigital.com/portfolio/ava-remarketing'],
                ['name' => 'Muslim Aid TORS MIS', 'summary' => 'Management information system case study.', 'url' => 'https://bergmediadigital.com/portfolio/muslim-aid-tors-mis'],
                ['name' => 'Hylaz', 'summary' => 'Client case study.', 'url' => 'https://bergmediadigital.com/portfolio/hylaz'],
                ['name' => 'Own products', 'summary' => 'THE AVA, MONWU, Resumizo, Courteazy and ShareToHere.', 'url' => 'https://bergmediadigital.com'],
            ],
            'markets' => ['United Kingdom', 'United Arab Emirates', 'Pakistan', 'International'],
            'pricing' => 'Website packages: Starter $799 (up to 5 pages), Business $1,499 (up to 10 pages, CMS), eCommerce $2,499 (up to 20 pages, payments, orders and inventory). Custom software, mobile, AI, branding, SEO and cloud work is quoted on scope.',
            'signature' => "The Berg Media Digital team\nbergmediadigital.com · info@bergmediadigital.com · +44 7879 969756",
            'is_default' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freelancer(): array
    {
        return [
            'name' => 'Vijesh Kumar Maheshwari',
            'kind' => ServiceProfile::KIND_FREELANCER,
            'tagline' => 'Principal Software Engineer: Laravel, Vue, React and Node. SaaS, e-commerce, ERP, POS.',
            'website' => 'https://vijeshmaheshwari.com',
            'email' => 'maheshwarivijeshkumar@gmail.com',
            'phone' => '+44 7879 969756',
            'location' => 'London, United Kingdom',
            'about' => "11+ years shipping production software, most recently as Principal Software Engineer at KuzTech (2024–2025); before that Team Lead at Brandigo (2023) and Senior Software Engineer at Paceglobal (2021–2022), with earlier work at Techizone, Printshop Sydney and Wiztech. Laravel on the back, Vue or React on the front, and systems a business depends on: shops, multi-vendor markets, SaaS tenants, ERP, accounting, POS and the APIs holding them together. 50+ projects for 30+ clients. Clear architecture, honest estimates, and releases that don't need a war room.\n\n(Paste the full resume here: it is used when drafting proposals.)",
            'services' => [
                ['name' => 'SaaS Development', 'summary' => 'Multi-tenant apps with billing, roles, and an admin that support can live in.', 'keywords' => ['saas', 'multi-tenant', 'multi tenant', 'subscription billing', 'stripe billing']],
                ['name' => 'E-commerce', 'summary' => 'Catalogue, checkout, stock and admin, built so a sale does not depend on luck.', 'keywords' => ['ecommerce', 'e-commerce', 'online store', 'checkout', 'shopping cart']],
                ['name' => 'Multi-vendor Marketplaces', 'summary' => 'Vendors, commissions, split checkout, and an operator desk that can settle a messy refund.', 'keywords' => ['marketplace', 'multi-vendor', 'multi vendor', 'vendor portal', 'seller portal']],
                ['name' => 'ERP Development', 'summary' => 'Finance, stock, HR and operations: the systems companies actually need.', 'keywords' => ['erp', 'inventory management', 'stock management', 'hrms', 'payroll', 'hr system', 'mis', 'management information system']],
                ['name' => 'POS Systems', 'summary' => 'Tills that stay fast: barcodes, shifts, offline-friendly sales, pharmacy batches.', 'keywords' => ['pos', 'point of sale', 'barcode system', 'retail system', 'pharmacy software']],
                ['name' => 'Accounting Software', 'summary' => 'Ledgers, invoices and reports a bookkeeper will trust.', 'keywords' => ['accounting software', 'accounting system', 'invoicing', 'general ledger', 'bookkeeping software', 'billing system']],
                ['name' => 'CRM Development', 'summary' => 'Pipelines and customer records that sales people open without being asked.', 'keywords' => ['crm', 'customer relationship', 'sales pipeline', 'lead management']],
                ['name' => 'Web Application Development', 'summary' => 'Custom web products a team can run for years, not just launch week.', 'keywords' => ['web application', 'web app', 'laravel', 'php', 'api development', 'rest api', 'graphql', 'backend', 'full stack', 'full-stack']],
            ],
            'skills' => ['laravel', 'php', 'vue', 'vue.js', 'nuxt', 'react', 'next.js', 'node.js', 'typescript', 'mysql', 'postgresql', 'rest api', 'graphql', 'docker', 'aws', 'golang', 'python', 'django', 'fastapi', 'stripe', 'github actions'],
            'portfolio' => [
                ['name' => 'Stafio', 'summary' => 'Multi-tenant HRMS, HCM, Pakistan payroll and practice CRM on one Laravel SaaS.', 'url' => 'https://vijeshmaheshwari.com'],
                ['name' => 'Enterprise ERP', 'summary' => 'Live ERP for finance, stock, HR and operations on Laravel, PostgreSQL and Vue.', 'url' => 'https://vijeshmaheshwari.com'],
                ['name' => 'Retail POS', 'summary' => 'Production retail POS: barcodes, shifts, tenders and close-of-day.', 'url' => 'https://vijeshmaheshwari.com'],
                ['name' => 'Beebooks', 'summary' => 'Accounting SaaS in Go and Nuxt with Stripe payments.', 'url' => 'https://vijeshmaheshwari.com'],
                ['name' => 'CPA Practice CRM', 'summary' => 'Multi-tenant CPA practice CRM with client portal, on Laravel.', 'url' => 'https://vijeshmaheshwari.com'],
                ['name' => 'Olive Organics', 'summary' => 'Laravel e-commerce store that converts on mobile.', 'url' => 'https://vijeshmaheshwari.com'],
            ],
            'markets' => ['United Kingdom', 'Pakistan', 'Remote / international'],
            'pricing' => null,
            'signature' => "Vijesh Kumar Maheshwari\nPrincipal Software Engineer · vijeshmaheshwari.com · +44 7879 969756",
            'is_default' => false,
        ];
    }
}
