<?php

use App\Enums\DocumentType;
use App\Enums\EmailTemplateType;
use App\Models\Team;
use App\Support\DocumentNumberer;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;

test('a new team gets Pakistani defaults', function () {
    $team = Team::factory()->create(['name' => 'Acme Traders']);

    $department = $team->defaultDepartment();

    expect($department)->not->toBeNull()
        ->and($department->name)->toBe('Acme Traders')
        ->and($department->is_default)->toBeTrue()
        ->and($team->taxRates()->count())->toBe(8)
        ->and($team->taxRates()->where('is_default', true)->value('rate'))->toBe('18.000')
        ->and($team->paymentTerms()->where('is_default', true)->value('days'))->toBe(30)
        ->and($team->workTypes()->count())->toBeGreaterThan(0)
        ->and($team->emailTemplates()->count())->toBe(count(EmailTemplateType::cases()));

    expect($team->teamSettings()->currency())->toBe('PKR')
        ->and($team->teamSettings()->fiscalYearStartMonth())->toBe(7);
});

test('the fiscal year runs from july to june', function () {
    $august = FiscalYear::for(CarbonImmutable::parse('2026-08-15'));
    $march = FiscalYear::for(CarbonImmutable::parse('2027-03-01'));
    $june = FiscalYear::for(CarbonImmutable::parse('2026-06-30'));

    expect($august->label())->toBe('2026-27')
        ->and($august->short())->toBe('2627')
        ->and($march->label())->toBe('2026-27')
        ->and($june->label())->toBe('2025-26')
        ->and($august->start()->toDateString())->toBe('2026-07-01')
        ->and($august->end()->toDateString())->toBe('2027-06-30');
});

test('document numbers are sequential and reset every fiscal year', function () {
    $team = Team::factory()->create();
    $numberer = app(DocumentNumberer::class);
    $department = $team->defaultDepartment();

    $first = $numberer->next($team, DocumentType::Invoice, CarbonImmutable::parse('2026-09-30'), $department);
    $second = $numberer->next($team, DocumentType::Invoice, CarbonImmutable::parse('2027-06-30'), $department);
    $nextYear = $numberer->next($team, DocumentType::Invoice, CarbonImmutable::parse('2027-07-01'), $department);

    expect($first)->toBe('INV-2627-0001')
        ->and($second)->toBe('INV-2627-0002')
        ->and($nextYear)->toBe('INV-2728-0001')
        ->and($numberer->preview($team, DocumentType::Invoice, CarbonImmutable::parse('2026-10-01'), $department))->toBe('INV-2627-0003');
});

test('document number sequences are kept per team', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $numberer = app(DocumentNumberer::class);
    $date = CarbonImmutable::parse('2026-09-30');

    expect($numberer->next($teamA, DocumentType::Quotation, $date, $teamA->defaultDepartment()))->toBe('QT-2627-0001')
        ->and($numberer->next($teamB, DocumentType::Quotation, $date, $teamB->defaultDepartment()))->toBe('QT-2627-0001')
        ->and($numberer->next($teamA, DocumentType::Ticket, $date))->toBe('TKT-0001');
});

test('custom numbering patterns are applied', function () {
    $team = Team::factory()->create([
        'settings' => ['numbering' => ['invoice' => ['pattern' => 'SI/{FY}/{SEQ}', 'padding' => 3]]],
    ]);

    $number = app(DocumentNumberer::class)->next($team, DocumentType::Invoice, CarbonImmutable::parse('2026-09-30'), $team->defaultDepartment());

    expect($number)->toBe('SI/2026-27/001');
});
