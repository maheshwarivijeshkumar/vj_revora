<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\Contracts\TenderSource;
use App\Domain\Tenders\DataObjects\TenderNotice;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Contract notices from Public Contracts Scotland.
 *
 * Their notices API returns the published form (F02) as JSON. noticeType 2
 * is a contract notice: something a supplier can still bid for. One call
 * covers the lookback window.
 *
 * @see https://api.publiccontractsscotland.gov.uk/v1/Notices
 */
final class PublicContractsScotlandSource implements TenderSource
{
    public const SOURCE = 'public-contracts-scotland';

    private const LOOKBACK_DAYS = 45;

    private const TIMEZONE = 'Europe/London';

    public function key(): string
    {
        return self::SOURCE;
    }

    /**
     * @return iterable<TenderNotice>
     */
    public function fetchAll(): iterable
    {
        $from = CarbonImmutable::now(self::TIMEZONE)->subDays(self::LOOKBACK_DAYS)->format('d-m-Y');
        $body = TenderHttp::json('https://api.publiccontractsscotland.gov.uk/v1/Notices?'.http_build_query([
            'noticeType' => 2,
            'outputType' => 1,
            'dateFrom' => $from,
        ]));

        $notices = $body['notices'] ?? null;

        if (! is_array($notices)) {
            throw new RuntimeException('Public Contracts Scotland returned an unexpected response.');
        }

        foreach ($notices as $notice) {
            if (! is_array($notice)) {
                continue;
            }

            $parsed = $this->notice($notice);

            if ($parsed !== null) {
                yield $parsed;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $notice
     */
    private function notice(array $notice): ?TenderNotice
    {
        $section = $notice['Form_Section'] ?? null;

        if (! is_array($section)) {
            return null;
        }

        $uuid = $section['Notice_UUID'] ?? null;
        $form = null;

        foreach ($section as $key => $value) {
            if ($key !== 'Notice_UUID' && is_array($value)) {
                $form = $value;
                break;
            }
        }

        if (! is_string($uuid) || $uuid === '' || $form === null) {
            return null;
        }

        $contract = $this->contract($form);
        $title = $this->text($contract['Title'] ?? null);

        if ($title === null) {
            return null;
        }

        $body = $form['Contracting_Body']['Address_Contracting_Body'] ?? [];
        $procedure = $form['Procedure'] ?? [];
        $participation = $this->text($form['Contracting_Body']['URL_Participation'] ?? null);

        return new TenderNotice(
            source: self::SOURCE,
            country: 'United Kingdom',
            reference: $uuid,
            title: $title,
            url: $participation ?? 'https://www.publiccontractsscotland.gov.uk/search/search_mainpage.aspx',
            description: $this->text($contract['Short_Descr'] ?? null),
            buyerReference: $this->text($contract['Reference_Number'] ?? null),
            organization: is_array($body) ? $this->text($body['OfficialName'] ?? null) : null,
            city: is_array($body) ? $this->text($body['Town'] ?? null) : null,
            sector: $this->text($contract['CPV_Main']['CPV_Code']['Code'] ?? null),
            procurementCategory: $this->category($contract['Type_Contract']['CType'] ?? null),
            tenderType: 'Contract notice',
            status: 'active',
            advertisedOn: $this->day($this->text($form['Complementary_Info']['Date_Dispatch_Notice'] ?? null)),
            closingAt: $this->deadline(
                is_array($procedure) ? $this->text($procedure['Date_Receipt_Tenders'] ?? null) : null,
                is_array($procedure) ? $this->text($procedure['Time_Receipt_Tenders'] ?? null) : null,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function contract(array $form): array
    {
        $contracts = $form['Object_Contract'] ?? [];

        if (! is_array($contracts)) {
            return [];
        }

        if (array_is_list($contracts)) {
            $first = $contracts[0] ?? [];

            return is_array($first) ? $first : [];
        }

        return $contracts;
    }

    private function category(mixed $value): ?string
    {
        return match (is_string($value) ? strtoupper($value) : '') {
            'SERVICES' => 'Services',
            'SUPPLIES' => 'Goods',
            'WORKS' => 'Works',
            default => $this->text(is_string($value) ? $value : null),
        };
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $clean === '' ? null : $clean;
    }

    private function day(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function deadline(?string $date, ?string $time): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($date.' '.($time ?? '23:59')), self::TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
