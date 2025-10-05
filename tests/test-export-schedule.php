<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Test_Export_Schedule extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        update_option('timezone_string', 'Europe/Paris');
    }

    public function test_hourly_schedule_uses_one_hour_increments() {
        $settings = [
            'frequency' => 'hourly',
            'run_time'  => '08:30',
        ];

        $reference = new DateTimeImmutable('2023-10-10 09:45:00', new DateTimeZone('Europe/Paris'));

        $timestamp = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference->getTimestamp());

        $next_run = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->assertSame('2023-10-10 10:30', $next_run->format('Y-m-d H:i'));
        $this->assertSame('30', $next_run->format('i'), 'Minute should match run_time setting.');
        $this->assertGreaterThan($reference->getTimestamp(), $timestamp);
        $this->assertLessThanOrEqual($reference->getTimestamp() + HOUR_IN_SECONDS, $timestamp);
    }

    public function test_twicedaily_schedule_uses_twelve_hour_increments() {
        $settings = [
            'frequency' => 'twicedaily',
            'run_time'  => '05:20',
        ];

        $reference = new DateTimeImmutable('2023-11-15 06:00:00', new DateTimeZone('Europe/Paris'));

        $timestamp = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference->getTimestamp());

        $next_run = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->assertSame('2023-11-15 17:20', $next_run->format('Y-m-d H:i'));
        $this->assertSame('20', $next_run->format('i'), 'Minute should match run_time setting.');
        $this->assertGreaterThan($reference->getTimestamp(), $timestamp);
        $this->assertLessThanOrEqual($reference->getTimestamp() + (12 * HOUR_IN_SECONDS), $timestamp);
    }

    public function test_daily_schedule_rolls_over_to_next_day() {
        $settings = [
            'frequency' => 'daily',
            'run_time'  => '14:45',
        ];

        $reference = new DateTimeImmutable('2023-09-12 15:00:00', new DateTimeZone('Europe/Paris'));

        $timestamp = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference->getTimestamp());

        $next_run = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->assertSame('2023-09-13 14:45', $next_run->format('Y-m-d H:i'));
        $this->assertSame('45', $next_run->format('i'), 'Minute should match run_time setting.');
    }

    public function test_weekly_schedule_rolls_over_to_next_week() {
        $settings = [
            'frequency' => 'weekly',
            'run_time'  => '09:10',
        ];

        $reference = new DateTimeImmutable('2023-08-01 12:00:00', new DateTimeZone('Europe/Paris'));

        $timestamp = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference->getTimestamp());

        $next_run = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->assertSame('2023-08-08 09:10', $next_run->format('Y-m-d H:i'));
        $this->assertSame('10', $next_run->format('i'), 'Minute should match run_time setting.');
    }
}
