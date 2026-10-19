<?php

namespace Tests\Unit;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Settings\Models\SystemSetting;
use App\Support\SensitiveData;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuditSafetyTest extends TestCase
{
    protected function tearDown(): void
    {
        AuditLog::clearBootedModels();

        parent::tearDown();
    }

    #[DataProvider('credentialKeys')]
    public function test_credential_key_variants_are_redacted_in_both_audit_snapshots(string $key): void
    {
        $values = ['invoice_number' => 'INV-TEST', 'nested' => [[$key => 'dummy-value', 'quantity' => '2.000']]];
        $expected = ['invoice_number' => 'INV-TEST', 'nested' => [[$key => '[REDACTED]', 'quantity' => '2.000']]];
        $audit = new AuditLog(['old_values' => $values, 'new_values' => $values]);

        $this->assertSame($expected, $audit->old_values);
        $this->assertSame($expected, $audit->new_values);
        $this->assertSame($expected, json_decode($audit->getAttributes()['old_values'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('dummy-value', $audit->toJson());
    }

    public static function credentialKeys(): array
    {
        return array_map(static fn (string $key): array => [$key], [
            'password', 'PASSWORD_CONFIRMATION', 'Remember-Token', 'token', 'secret',
            'API.Key', 'APP_KEY', 'DB PASSWORD', 'Authorization', 'COOKIE', 'Private_Key',
            'clientSecret', 'access_token', 'backup.credentials',
        ]);
    }

    public function test_preencoded_json_is_redacted_once_and_null_remains_null(): void
    {
        $audit = new AuditLog;
        $audit->old_values = '{"profile":{"api_key":"dummy-value"},"customer_name":"Business name"}';

        $this->assertSame(['profile' => ['api_key' => '[REDACTED]'], 'customer_name' => 'Business name'], $audit->old_values);
        $this->assertIsArray(json_decode($audit->getAttributes()['old_values'], true, 512, JSON_THROW_ON_ERROR));

        $audit->new_values = null;

        $this->assertNull($audit->new_values);
        $this->assertNull($audit->getAttributes()['new_values']);
    }

    public function test_hydrated_legacy_audit_values_are_redacted_when_read(): void
    {
        $audit = new AuditLog;
        $audit->setRawAttributes(['old_values' => '{"Password":"dummy-value","total":"125.50"}']);

        $this->assertSame(['Password' => '[REDACTED]', 'total' => '125.50'], $audit->old_values);
        $this->assertSame(['Password' => '[REDACTED]', 'total' => '125.50'], $audit->toArray()['old_values']);
    }

    public function test_unrelated_business_fields_and_scalar_settings_are_preserved(): void
    {
        $values = ['monkey' => 'illustration', 'secretary_name' => 'Office contact', 'sku' => 'PART-TEST', 'total' => '12.50'];

        $this->assertSame($values, SensitiveData::redact($values));

        $setting = new SystemSetting(['group' => 'business', 'key' => 'name', 'value' => 'Nilambara Electricals']);
        $setting->assertSafeForStorage();

        $this->assertSame('Nilambara Electricals', $setting->value);
        $this->assertSame($values, (new SystemSetting(['value' => $values]))->value);

        foreach (['123', 'true', 'null'] as $businessString) {
            $this->assertSame($businessString, (new SystemSetting(['value' => $businessString]))->value);
        }
    }

    #[DataProvider('unsafeSettingNames')]
    public function test_settings_reject_credential_names_on_assignment(string $attribute, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SystemSetting)->setAttribute($attribute, $name);
    }

    public static function unsafeSettingNames(): array
    {
        return [['key', 'DB-PASSWORD'], ['key', 'private.key'], ['group', 'credentials'], ['group', 'backup_secret']];
    }

    public function test_settings_reject_nested_sensitive_fields_in_json_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SystemSetting(['value' => '{"business":{"entries":[{"Api-Key":"dummy-value"}]}}']);
    }

    public function test_saving_rechecks_unsafe_raw_attributes_without_a_database(): void
    {
        $previousDispatcher = SystemSetting::getEventDispatcher();
        SystemSetting::clearBootedModels();
        SystemSetting::setEventDispatcher(new Dispatcher(new Container));

        try {
            $setting = new SystemSetting;
            $setting->setRawAttributes(['group' => 'business', 'key' => 'integration', 'value' => '{"SECRET":"dummy-value"}']);

            $this->expectException(InvalidArgumentException::class);

            SystemSetting::getEventDispatcher()->dispatch('eloquent.saving: '.SystemSetting::class, [$setting]);
        } finally {
            SystemSetting::clearBootedModels();

            if ($previousDispatcher) {
                SystemSetting::setEventDispatcher($previousDispatcher);
            } else {
                SystemSetting::unsetEventDispatcher();
            }
        }
    }
}
