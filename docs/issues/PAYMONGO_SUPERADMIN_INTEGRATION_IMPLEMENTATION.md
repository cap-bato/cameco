# PayMongo API Integration - Superadmin Implementation

**Feature:** PayMongo API Integration & System Configuration  
**Role:** Superadmin Only  
**Module:** System Settings > Integrations  
**Priority:** HIGH  
**Estimated Duration:** 3-4 days  
**Current Status:** ⏳ PLANNING - No PayMongo integration exists yet

---

## 📋 Executive Summary

Implement PayMongo API integration allowing the Superadmin to:
- Configure PayMongo API credentials (public key, secret key)
- Set up webhook endpoints for payment status updates
- Toggle between test and live mode
- View integration health status and logs
- Test API connection
- Manage webhook secrets

This is **system-level configuration** that enables the payment infrastructure. After Superadmin configures this, Office Admin can configure which payment methods to use, and Payroll Officers can execute payments.

---

## 🎯 Goals & Requirements

### Primary Goals:
1. ✅ Secure storage of PayMongo API credentials (encrypted)
2. ✅ Webhook endpoint for payment status updates
3. ✅ Test/Live mode toggle
4. ✅ API connection health check
5. ✅ Integration logs and error tracking
6. ✅ Superadmin-only access (RBAC)

### Security Requirements:
- ✅ Encrypt API secret keys in database
- ✅ Mask sensitive credentials in UI
- ✅ Validate webhook signatures
- ✅ Audit log all configuration changes
- ✅ Role-based access control (Superadmin only)

---

## 📊 Current State Analysis

### ✅ Already Exists:
- ✅ Superadmin role and permissions system
- ✅ System settings infrastructure
- ✅ Audit logging system

### ⚠️ Needs Implementation:
- ❌ PayMongo configuration table
- ❌ PayMongo service layer (API client)
- ❌ Webhook controller and routes
- ❌ Superadmin settings page for PayMongo
- ❌ API connection test functionality
- ❌ Integration logs storage

---

## Phase 1: Database Schema & Models

**Duration:** 0.5 days

### Task 1.1: Create PayMongo Configuration Migration

**Goal:** Store PayMongo API credentials and webhook configuration.

**Implementation Steps:**

1. **Create Migration:**
   ```bash
   php artisan make:migration create_paymongo_configurations_table
   ```

2. **Migration Content:**

Create file: `database/migrations/YYYY_MM_DD_create_paymongo_configurations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymongo_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('public_key')->nullable();
            $table->text('secret_key')->nullable(); // Encrypted
            $table->text('webhook_secret')->nullable(); // Encrypted
            $table->boolean('is_live_mode')->default(false);
            $table->boolean('is_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });

        Schema::create('paymongo_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // api_request, webhook, test_connection, error
            $table->string('method')->nullable(); // GET, POST, etc.
            $table->string('endpoint')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            
            $table->index(['event_type', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('paymongo_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('paymongo_webhook_id')->unique();
            $table->string('event_type'); // payment.paid, payment.failed, etc.
            $table->string('resource_type'); // payment, source, etc.
            $table->string('resource_id');
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['event_type', 'processed']);
            $table->index('resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymongo_webhooks');
        Schema::dropIfExists('paymongo_logs');
        Schema::dropIfExists('paymongo_configurations');
    }
};
```

**Files to Create:**
- `database/migrations/YYYY_MM_DD_create_paymongo_configurations_table.php`

**Run Migration:**
```bash
php artisan migrate
```

---

### Task 1.2: Create PayMongo Models

**Goal:** Create Eloquent models for PayMongo configuration and logs.

**Implementation Steps:**

1. **Create Models:**
   ```bash
   php artisan make:model PayMongoConfiguration
   php artisan make:model PayMongoLog
   php artisan make:model PayMongoWebhook
   ```

2. **PayMongoConfiguration Model:**

Create file: `app/Models/PayMongoConfiguration.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PayMongoConfiguration extends Model
{
    protected $fillable = [
        'public_key',
        'secret_key',
        'webhook_secret',
        'is_live_mode',
        'is_enabled',
        'webhook_url',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
    ];

    protected $casts = [
        'is_live_mode' => 'boolean',
        'is_enabled' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    /**
     * Get the decrypted secret key
     */
    public function getDecryptedSecretKeyAttribute(): ?string
    {
        return $this->secret_key ? Crypt::decryptString($this->secret_key) : null;
    }

    /**
     * Set the encrypted secret key
     */
    public function setSecretKeyAttribute($value): void
    {
        $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get the decrypted webhook secret
     */
    public function getDecryptedWebhookSecretAttribute(): ?string
    {
        return $this->webhook_secret ? Crypt::decryptString($this->webhook_secret) : null;
    }

    /**
     * Set the encrypted webhook secret
     */
    public function setWebhookSecretAttribute($value): void
    {
        $this->attributes['webhook_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Mask the public key for display
     */
    public function getMaskedPublicKeyAttribute(): string
    {
        if (!$this->public_key) return 'Not configured';
        
        return substr($this->public_key, 0, 12) . '••••••••' . substr($this->public_key, -4);
    }

    /**
     * Check if configuration is complete
     */
    public function isConfigured(): bool
    {
        return !empty($this->public_key) && !empty($this->secret_key);
    }

    /**
     * Get the singleton configuration
     */
    public static function current(): self
    {
        return self::firstOrCreate([]);
    }
}
```

3. **PayMongoLog Model:**

Create file: `app/Models/PayMongoLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayMongoLog extends Model
{
    protected $fillable = [
        'event_type',
        'method',
        'endpoint',
        'status_code',
        'request_data',
        'response_data',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an API request
     */
    public static function logApiRequest(
        string $method,
        string $endpoint,
        ?array $requestData = null,
        ?array $responseData = null,
        ?int $statusCode = null,
        ?string $errorMessage = null
    ): self {
        return self::create([
            'event_type' => 'api_request',
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Log a webhook event
     */
    public static function logWebhook(array $payload, ?string $errorMessage = null): self
    {
        return self::create([
            'event_type' => 'webhook',
            'request_data' => $payload,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log a connection test
     */
    public static function logConnectionTest(bool $success, ?string $errorMessage = null): self
    {
        return self::create([
            'event_type' => 'test_connection',
            'status_code' => $success ? 200 : 500,
            'error_message' => $errorMessage,
            'user_id' => auth()->id(),
        ]);
    }
}
```

4. **PayMongoWebhook Model:**

Create file: `app/Models/PayMongoWebhook.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayMongoWebhook extends Model
{
    protected $fillable = [
        'paymongo_webhook_id',
        'event_type',
        'resource_type',
        'resource_id',
        'payload',
        'processed',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Mark webhook as processed
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'processed' => true,
            'processed_at' => now(),
        ]);
    }

    /**
     * Scope: Unprocessed webhooks
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope: By event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
```

**Files to Create:**
- `app/Models/PayMongoConfiguration.php`
- `app/Models/PayMongoLog.php`
- `app/Models/PayMongoWebhook.php`

**Verification:**
- ✅ Models use encrypted attributes for sensitive data
- ✅ Masking functionality for public display
- ✅ Helper methods for logging
- ✅ Singleton pattern for configuration

---

## Phase 2: PayMongo Service Layer

**Duration:** 1 day

### Task 2.1: Create PayMongo API Client Service

**Goal:** Build a service class to interact with PayMongo API.

**Implementation Steps:**

1. **Install Guzzle HTTP Client (if not already installed):**
   ```bash
   composer require guzzlehttp/guzzle
   ```

2. **Create PayMongo Service:**

Create file: `app/Services/PayMongoService.php`

```php
<?php

namespace App\Services;

use App\Models\PayMongoConfiguration;
use App\Models\PayMongoLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    protected Client $client;
    protected PayMongoConfiguration $config;
    protected string $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->config = PayMongoConfiguration::current();
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        if (!$this->config->isConfigured()) {
            return [
                'success' => false,
                'message' => 'PayMongo not configured. Please add API keys.',
            ];
        }

        try {
            // Test by retrieving payment methods
            $response = $this->client->get('/payment_methods', [
                'headers' => $this->getHeaders(),
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            PayMongoLog::logConnectionTest(true);

            $this->config->update([
                'last_tested_at' => now(),
                'last_test_status' => 'success',
                'last_test_error' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Connection successful!',
                'status_code' => $statusCode,
                'mode' => $this->config->is_live_mode ? 'Live' : 'Test',
            ];

        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            
            PayMongoLog::logConnectionTest(false, $errorMessage);

            $this->config->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage,
            ];
        }
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $response = $this->client->post('/payment_intents', [
                'headers' => $this->getHeaders(),
                'json' => [
                    'data' => [
                        'attributes' => $data,
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            PayMongoLog::logApiRequest(
                'POST',
                '/payment_intents',
                $data,
                $body,
                $response->getStatusCode()
            );

            return [
                'success' => true,
                'data' => $body['data'] ?? null,
            ];

        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            
            PayMongoLog::logApiRequest(
                'POST',
                '/payment_intents',
                $data,
                null,
                $e->getCode(),
                $errorMessage
            );

            return [
                'success' => false,
                'message' => $errorMessage,
            ];
        }
    }

    /**
     * Create a payment source (for GCash, Maya, etc.)
     */
    public function createSource(array $data): array
    {
        try {
            $response = $this->client->post('/sources', [
                'headers' => $this->getHeaders(),
                'json' => [
                    'data' => [
                        'attributes' => $data,
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            PayMongoLog::logApiRequest(
                'POST',
                '/sources',
                $data,
                $body,
                $response->getStatusCode()
            );

            return [
                'success' => true,
                'data' => $body['data'] ?? null,
            ];

        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            
            PayMongoLog::logApiRequest(
                'POST',
                '/sources',
                $data,
                null,
                $e->getCode(),
                $errorMessage
            );

            return [
                'success' => false,
                'message' => $errorMessage,
            ];
        }
    }

    /**
     * Retrieve a payment
     */
    public function retrievePayment(string $paymentId): array
    {
        try {
            $response = $this->client->get("/payments/{$paymentId}", [
                'headers' => $this->getHeaders(),
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            PayMongoLog::logApiRequest(
                'GET',
                "/payments/{$paymentId}",
                null,
                $body,
                $response->getStatusCode()
            );

            return [
                'success' => true,
                'data' => $body['data'] ?? null,
            ];

        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            
            PayMongoLog::logApiRequest(
                'GET',
                "/payments/{$paymentId}",
                null,
                null,
                $e->getCode(),
                $errorMessage
            );

            return [
                'success' => false,
                'message' => $errorMessage,
            ];
        }
    }

    /**
     * List available payment methods
     */
    public function getPaymentMethods(): array
    {
        try {
            $response = $this->client->get('/payment_methods', [
                'headers' => $this->getHeaders(),
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'data' => $body['data'] ?? [],
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get request headers with authentication
     */
    protected function getHeaders(): array
    {
        $secretKey = $this->config->decrypted_secret_key;
        
        return [
            'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $webhookSecret = $this->config->decrypted_webhook_secret;
        
        if (!$webhookSecret) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', json_encode($payload), $webhookSecret);
        
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Check if PayMongo is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config->is_enabled && $this->config->isConfigured();
    }

    /**
     * Check if in live mode
     */
    public function isLiveMode(): bool
    {
        return $this->config->is_live_mode;
    }
}
```

**Files to Create:**
- `app/Services/PayMongoService.php`

**Verification:**
- ✅ API client configured with Guzzle
- ✅ Authentication headers use Base64 encoded secret key
- ✅ All API calls are logged
- ✅ Error handling and logging
- ✅ Webhook signature verification

---

## Phase 3: Backend - Controller & Routes

**Duration:** 0.75 days

### Task 3.1: Create Superadmin PayMongo Controller

**Goal:** Create controller for Superadmin to manage PayMongo configuration.

**Implementation Steps:**

Create file: `app/Http/Controllers/Admin/PayMongoConfigurationController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayMongoConfiguration;
use App\Models\PayMongoLog;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayMongoConfigurationController extends Controller
{
    protected PayMongoService $payMongoService;

    public function __construct(PayMongoService $payMongoService)
    {
        $this->middleware(['auth', 'role:Superadmin']);
        $this->payMongoService = $payMongoService;
    }

    /**
     * Display PayMongo configuration page
     */
    public function index(): Response
    {
        $config = PayMongoConfiguration::current();

        $logs = PayMongoLog::with('user:id,name')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'method' => $log->method,
                'endpoint' => $log->endpoint,
                'status_code' => $log->status_code,
                'error_message' => $log->error_message,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Admin/Integrations/PayMongo/Index', [
            'configuration' => [
                'id' => $config->id,
                'public_key' => $config->public_key,
                'masked_public_key' => $config->masked_public_key,
                'has_secret_key' => !empty($config->secret_key),
                'has_webhook_secret' => !empty($config->webhook_secret),
                'is_live_mode' => $config->is_live_mode,
                'is_enabled' => $config->is_enabled,
                'webhook_url' => $config->webhook_url ?? url('/webhooks/paymongo'),
                'last_tested_at' => $config->last_tested_at?->format('Y-m-d H:i:s'),
                'last_test_status' => $config->last_test_status,
                'last_test_error' => $config->last_test_error,
                'is_configured' => $config->isConfigured(),
            ],
            'logs' => $logs,
        ]);
    }

    /**
     * Update PayMongo configuration
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'public_key' => 'nullable|string|max:255',
            'secret_key' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'is_live_mode' => 'boolean',
            'is_enabled' => 'boolean',
        ]);

        $config = PayMongoConfiguration::current();

        // Only update fields that are provided
        $updateData = [];
        
        if ($request->has('public_key')) {
            $updateData['public_key'] = $validated['public_key'];
        }
        
        if ($request->has('secret_key') && !empty($validated['secret_key'])) {
            $updateData['secret_key'] = $validated['secret_key'];
        }
        
        if ($request->has('webhook_secret') && !empty($validated['webhook_secret'])) {
            $updateData['webhook_secret'] = $validated['webhook_secret'];
        }
        
        if ($request->has('is_live_mode')) {
            $updateData['is_live_mode'] = $validated['is_live_mode'];
        }
        
        if ($request->has('is_enabled')) {
            $updateData['is_enabled'] = $validated['is_enabled'];
        }

        $config->update($updateData);

        // Log configuration change
        activity()
            ->causedBy(auth()->user())
            ->performedOn($config)
            ->withProperties(['changes' => array_keys($updateData)])
            ->log('PayMongo configuration updated');

        return response()->json([
            'success' => true,
            'message' => 'PayMongo configuration updated successfully.',
            'configuration' => [
                'masked_public_key' => $config->masked_public_key,
                'has_secret_key' => !empty($config->secret_key),
                'has_webhook_secret' => !empty($config->webhook_secret),
                'is_live_mode' => $config->is_live_mode,
                'is_enabled' => $config->is_enabled,
            ],
        ]);
    }

    /**
     * Test PayMongo API connection
     */
    public function testConnection()
    {
        $result = $this->payMongoService->testConnection();

        return response()->json($result);
    }

    /**
     * Get integration logs
     */
    public function logs()
    {
        $logs = PayMongoLog::with('user:id,name')
            ->latest()
            ->paginate(50)
            ->through(fn($log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'method' => $log->method,
                'endpoint' => $log->endpoint,
                'status_code' => $log->status_code,
                'error_message' => $log->error_message,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json($logs);
    }

    /**
     * Clear old logs
     */
    public function clearLogs()
    {
        $days = request()->input('days', 30);
        
        $deleted = PayMongoLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} log entries older than {$days} days.",
        ]);
    }
}
```

**Files to Create:**
- `app/Http/Controllers/Admin/PayMongoConfigurationController.php`

---

### Task 3.2: Create Webhook Controller

**Goal:** Handle PayMongo webhook events.

**Implementation Steps:**

Create file: `app/Http/Controllers/Webhooks/PayMongoWebhookController.php`

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PayMongoWebhook;
use App\Models\PayMongoLog;
use App\Services\PayMongoService;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    protected PayMongoService $payMongoService;

    public function __construct(PayMongoService $payMongoService)
    {
        $this->payMongoService = $payMongoService;
    }

    /**
     * Handle incoming webhook from PayMongo
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('PayMongo-Signature');

        // Verify webhook signature
        if (!$this->payMongoService->verifyWebhookSignature($payload, $signature)) {
            PayMongoLog::logWebhook($payload, 'Invalid webhook signature');
            
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            // Extract event data
            $eventType = $payload['data']['attributes']['type'] ?? 'unknown';
            $resource = $payload['data']['attributes']['data'] ?? [];
            $resourceType = $resource['type'] ?? 'unknown';
            $resourceId = $resource['id'] ?? null;

            // Store webhook for processing
            $webhook = PayMongoWebhook::create([
                'paymongo_webhook_id' => $payload['data']['id'] ?? uniqid(),
                'event_type' => $eventType,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'payload' => $payload,
                'processed' => false,
            ]);

            PayMongoLog::logWebhook($payload);

            // Process webhook based on event type
            $this->processWebhook($webhook);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            PayMongoLog::logWebhook($payload, $e->getMessage());
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process webhook event
     */
    protected function processWebhook(PayMongoWebhook $webhook): void
    {
        switch ($webhook->event_type) {
            case 'payment.paid':
                $this->handlePaymentPaid($webhook);
                break;
            
            case 'payment.failed':
                $this->handlePaymentFailed($webhook);
                break;
            
            case 'source.chargeable':
                $this->handleSourceChargeable($webhook);
                break;
            
            default:
                // Log unhandled event type
                \Log::info("Unhandled PayMongo webhook event: {$webhook->event_type}");
        }

        $webhook->markAsProcessed();
    }

    /**
     * Handle payment.paid event
     */
    protected function handlePaymentPaid(PayMongoWebhook $webhook): void
    {
        $paymentData = $webhook->payload['data']['attributes']['data'] ?? [];
        $paymentId = $paymentData['id'] ?? null;

        // TODO: Update payment record in your system
        // Example: Payment::where('paymongo_payment_id', $paymentId)->update(['status' => 'paid']);

        \Log::info("Payment paid: {$paymentId}");
    }

    /**
     * Handle payment.failed event
     */
    protected function handlePaymentFailed(PayMongoWebhook $webhook): void
    {
        $paymentData = $webhook->payload['data']['attributes']['data'] ?? [];
        $paymentId = $paymentData['id'] ?? null;

        // TODO: Update payment record in your system
        // Example: Payment::where('paymongo_payment_id', $paymentId)->update(['status' => 'failed']);

        \Log::info("Payment failed: {$paymentId}");
    }

    /**
     * Handle source.chargeable event
     */
    protected function handleSourceChargeable(PayMongoWebhook $webhook): void
    {
        $sourceData = $webhook->payload['data']['attributes']['data'] ?? [];
        $sourceId = $sourceData['id'] ?? null;

        // TODO: Create payment using this source
        // Example: $this->payMongoService->createPaymentIntent(...);

        \Log::info("Source chargeable: {$sourceId}");
    }
}
```

**Files to Create:**
- `app/Http/Controllers/Webhooks/PayMongoWebhookController.php`

---

### Task 3.3: Add Routes

**Goal:** Configure routes for PayMongo configuration and webhooks.

**Implementation Steps:**

1. **Update `routes/admin.php` (or create if doesn't exist):**

```php
<?php

use App\Http\Controllers\Admin\PayMongoConfigurationController;

// PayMongo Integration (Superadmin only)
Route::middleware(['auth', 'role:Superadmin'])
    ->prefix('integrations/paymongo')
    ->name('admin.integrations.paymongo.')
    ->group(function () {
        Route::get('/', [PayMongoConfigurationController::class, 'index'])->name('index');
        Route::put('/configuration', [PayMongoConfigurationController::class, 'update'])->name('update');
        Route::post('/test-connection', [PayMongoConfigurationController::class, 'testConnection'])->name('test');
        Route::get('/logs', [PayMongoConfigurationController::class, 'logs'])->name('logs');
        Route::delete('/logs', [PayMongoConfigurationController::class, 'clearLogs'])->name('logs.clear');
    });
```

2. **Update `routes/web.php` - Add webhook route:**

```php
<?php

use App\Http\Controllers\Webhooks\PayMongoWebhookController;

// PayMongo Webhook (Public, no auth)
Route::post('/webhooks/paymongo', [PayMongoWebhookController::class, 'handle'])
    ->name('webhooks.paymongo');
```

**Files to Modify:**
- `routes/admin.php` (or `routes/web.php` if admin.php doesn't exist)
- `routes/web.php` (for webhook route)

**Verification:**
- ✅ Routes require Superadmin role
- ✅ Webhook route is publicly accessible (for PayMongo callbacks)
- ✅ Route names follow convention

---

## Phase 4: Frontend - Superadmin Configuration Page

**Duration:** 1.5 days

### Task 4.1: Create PayMongo Configuration Page

**Goal:** Build Superadmin UI for PayMongo configuration.

**Implementation Steps:**

1. **Create Directory:**
   ```bash
   mkdir -p resources/js/pages/Admin/Integrations/PayMongo
   ```

2. **Create Index Page:**

Create file: `resources/js/pages/Admin/Integrations/PayMongo/Index.tsx`

```tsx
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  CheckCircle,
  XCircle,
  Key,
  Webhook,
  Activity,
  Eye,
  EyeOff,
  TestTube,
  Save,
  AlertTriangle,
  Copy,
} from 'lucide-react';
import axios from 'axios';
import { PageProps } from '@/types';

interface PayMongoConfiguration {
  id: number;
  public_key: string;
  masked_public_key: string;
  has_secret_key: boolean;
  has_webhook_secret: boolean;
  is_live_mode: boolean;
  is_enabled: boolean;
  webhook_url: string;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_error: string | null;
  is_configured: boolean;
}

interface PayMongoLog {
  id: number;
  event_type: string;
  method: string | null;
  endpoint: string | null;
  status_code: number | null;
  error_message: string | null;
  user_name: string | null;
  created_at: string;
}

interface PayMongoPageProps extends PageProps {
  configuration: PayMongoConfiguration;
  logs: PayMongoLog[];
}

export default function PayMongoIndex({ configuration, logs }: PayMongoPageProps) {
  const [showSecretKey, setShowSecretKey] = useState(false);
  const [showWebhookSecret, setShowWebhookSecret] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<any>(null);

  const { data, setData, put, processing, errors } = useForm({
    public_key: configuration.public_key || '',
    secret_key: '',
    webhook_secret: '',
    is_live_mode: configuration.is_live_mode,
    is_enabled: configuration.is_enabled,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    put(route('admin.integrations.paymongo.update'), {
      preserveScroll: true,
      onSuccess: () => {
        // Clear sensitive fields after successful update
        setData('secret_key', '');
        setData('webhook_secret', '');
      },
    });
  };

  const handleTestConnection = async () => {
    setTesting(true);
    setTestResult(null);

    try {
      const response = await axios.post(route('admin.integrations.paymongo.test'));
      setTestResult(response.data);
    } catch (error: any) {
      setTestResult({
        success: false,
        message: error.response?.data?.message || 'Connection test failed',
      });
    } finally {
      setTesting(false);
    }
  };

  const copyWebhookUrl = () => {
    navigator.clipboard.writeText(configuration.webhook_url);
    // Show toast notification (implement toast system)
  };

  const getStatusColor = (status: string | null) => {
    switch (status) {
      case 'success': return 'text-green-600';
      case 'failed': return 'text-red-600';
      default: return 'text-gray-600';
    }
  };

  const getStatusBadge = (statusCode: number | null) => {
    if (statusCode === null) return null;
    if (statusCode >= 200 && statusCode < 300) {
      return <Badge className="bg-green-100 text-green-800">Success</Badge>;
    }
    return <Badge variant="destructive">Error</Badge>;
  };

  return (
    <AppLayout>
      <Head title="PayMongo Integration - System Settings" />

      <div className="p-6 max-w-7xl mx-auto space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900">PayMongo Integration</h1>
          <p className="text-gray-600 mt-1">
            Configure PayMongo API credentials and manage payment integrations (Superadmin Only)
          </p>
        </div>

        {/* Status Banner */}
        {!configuration.is_configured && (
          <Alert variant="destructive">
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription>
              PayMongo is not configured. Please enter your API credentials below to enable payment processing.
            </AlertDescription>
          </Alert>
        )}

        {configuration.is_configured && !configuration.is_enabled && (
          <Alert>
            <Activity className="h-4 w-4" />
            <AlertDescription>
              PayMongo is configured but currently disabled. Enable it to start processing payments.
            </AlertDescription>
          </Alert>
        )}

        <Tabs defaultValue="configuration" className="space-y-6">
          <TabsList>
            <TabsTrigger value="configuration">Configuration</TabsTrigger>
            <TabsTrigger value="logs">Integration Logs</TabsTrigger>
          </TabsList>

          {/* Configuration Tab */}
          <TabsContent value="configuration" className="space-y-6">
            {/* Status Card */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  {configuration.is_configured && configuration.is_enabled ? (
                    <CheckCircle className="h-5 w-5 text-green-600" />
                  ) : (
                    <XCircle className="h-5 w-5 text-gray-400" />
                  )}
                  Integration Status
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Configuration</div>
                    <div className="font-semibold">
                      {configuration.is_configured ? (
                        <span className="text-green-600">✓ Configured</span>
                      ) : (
                        <span className="text-gray-500">Not Configured</span>
                      )}
                    </div>
                  </div>
                  
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Mode</div>
                    <div className="font-semibold">
                      {configuration.is_live_mode ? (
                        <Badge className="bg-red-100 text-red-800">Live Mode</Badge>
                      ) : (
                        <Badge className="bg-yellow-100 text-yellow-800">Test Mode</Badge>
                      )}
                    </div>
                  </div>
                  
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Status</div>
                    <div className="font-semibold">
                      {configuration.is_enabled ? (
                        <Badge className="bg-green-100 text-green-800">Enabled</Badge>
                      ) : (
                        <Badge variant="secondary">Disabled</Badge>
                      )}
                    </div>
                  </div>
                </div>

                {configuration.last_tested_at && (
                  <div className="mt-4 pt-4 border-t">
                    <div className="text-sm text-gray-600 mb-1">Last Connection Test</div>
                    <div className="flex items-center gap-2">
                      <span className={getStatusColor(configuration.last_test_status)}>
                        {configuration.last_test_status === 'success' ? '✓' : '✗'}
                      </span>
                      <span className="text-sm">{configuration.last_tested_at}</span>
                    </div>
                    {configuration.last_test_error && (
                      <div className="mt-2 text-sm text-red-600">
                        {configuration.last_test_error}
                      </div>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Configuration Form */}
            <form onSubmit={handleSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Key className="h-5 w-5" />
                    API Credentials
                  </CardTitle>
                  <CardDescription>
                    Enter your PayMongo API keys. Get them from{' '}
                    <a
                      href="https://dashboard.paymongo.com/developers"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-blue-600 hover:underline"
                    >
                      PayMongo Dashboard
                    </a>
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  {/* Public Key */}
                  <div>
                    <Label htmlFor="public_key">Public Key *</Label>
                    <Input
                      id="public_key"
                      value={data.public_key}
                      onChange={(e) => setData('public_key', e.target.value)}
                      placeholder="pk_test_xxxxxxxxxx or pk_live_xxxxxxxxxx"
                      disabled={processing}
                    />
                    {configuration.has_secret_key && (
                      <p className="text-xs text-gray-500 mt-1">
                        Current: {configuration.masked_public_key}
                      </p>
                    )}
                    {errors.public_key && (
                      <p className="text-sm text-red-600 mt-1">{errors.public_key}</p>
                    )}
                  </div>

                  {/* Secret Key */}
                  <div>
                    <Label htmlFor="secret_key">Secret Key *</Label>
                    <div className="relative">
                      <Input
                        id="secret_key"
                        type={showSecretKey ? 'text' : 'password'}
                        value={data.secret_key}
                        onChange={(e) => setData('secret_key', e.target.value)}
                        placeholder={configuration.has_secret_key ? '••••••••••' : 'sk_test_xxxxxxxxxx or sk_live_xxxxxxxxxx'}
                        disabled={processing}
                      />
                      <button
                        type="button"
                        onClick={() => setShowSecretKey(!showSecretKey)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                      >
                        {showSecretKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    {configuration.has_secret_key && (
                      <p className="text-xs text-green-600 mt-1">
                        ✓ Secret key is configured (leave blank to keep current)
                      </p>
                    )}
                    {errors.secret_key && (
                      <p className="text-sm text-red-600 mt-1">{errors.secret_key}</p>
                    )}
                  </div>

                  {/* Webhook Secret */}
                  <div>
                    <Label htmlFor="webhook_secret">Webhook Secret (Optional)</Label>
                    <div className="relative">
                      <Input
                        id="webhook_secret"
                        type={showWebhookSecret ? 'text' : 'password'}
                        value={data.webhook_secret}
                        onChange={(e) => setData('webhook_secret', e.target.value)}
                        placeholder={configuration.has_webhook_secret ? '••••••••••' : 'whsec_xxxxxxxxxx'}
                        disabled={processing}
                      />
                      <button
                        type="button"
                        onClick={() => setShowWebhookSecret(!showWebhookSecret)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                      >
                        {showWebhookSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    {configuration.has_webhook_secret && (
                      <p className="text-xs text-green-600 mt-1">
                        ✓ Webhook secret is configured
                      </p>
                    )}
                    {errors.webhook_secret && (
                      <p className="text-sm text-red-600 mt-1">{errors.webhook_secret}</p>
                    )}
                  </div>

                  {/* Mode Toggle */}
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <Label htmlFor="is_live_mode">Live Mode</Label>
                      <p className="text-sm text-gray-600">
                        Enable live mode to process real payments (requires live API keys)
                      </p>
                    </div>
                    <Switch
                      id="is_live_mode"
                      checked={data.is_live_mode}
                      onCheckedChange={(checked) => setData('is_live_mode', checked)}
                      disabled={processing}
                    />
                  </div>

                  {/* Enable Toggle */}
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <Label htmlFor="is_enabled">Enable PayMongo</Label>
                      <p className="text-sm text-gray-600">
                        Allow payment processing through PayMongo
                      </p>
                    </div>
                    <Switch
                      id="is_enabled"
                      checked={data.is_enabled}
                      onCheckedChange={(checked) => setData('is_enabled', checked)}
                      disabled={processing}
                    />
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-3 pt-4 border-t">
                    <Button type="submit" disabled={processing} className="gap-2">
                      <Save className="h-4 w-4" />
                      Save Configuration
                    </Button>
                    
                    <Button
                      type="button"
                      variant="outline"
                      onClick={handleTestConnection}
                      disabled={testing || !configuration.is_configured}
                      className="gap-2"
                    >
                      <TestTube className="h-4 w-4" />
                      {testing ? 'Testing...' : 'Test Connection'}
                    </Button>
                  </div>

                  {/* Test Result */}
                  {testResult && (
                    <Alert variant={testResult.success ? 'default' : 'destructive'} className="mt-4">
                      {testResult.success ? (
                        <CheckCircle className="h-4 w-4 text-green-600" />
                      ) : (
                        <XCircle className="h-4 w-4" />
                      )}
                      <AlertDescription>
                        <strong>{testResult.success ? 'Success!' : 'Failed'}</strong>
                        <p className="mt-1">{testResult.message}</p>
                        {testResult.mode && (
                          <p className="text-sm mt-1">Mode: {testResult.mode}</p>
                        )}
                      </AlertDescription>
                    </Alert>
                  )}
                </CardContent>
              </Card>
            </form>

            {/* Webhook Configuration */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Webhook className="h-5 w-5" />
                  Webhook Configuration
                </CardTitle>
                <CardDescription>
                  Configure this webhook URL in your PayMongo Dashboard to receive payment status updates
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center gap-2">
                  <Input
                    value={configuration.webhook_url}
                    readOnly
                    className="font-mono text-sm"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={copyWebhookUrl}
                  >
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
                <p className="text-sm text-gray-600 mt-2">
                  Add this URL to your PayMongo webhooks in the{' '}
                  <a
                    href="https://dashboard.paymongo.com/webhooks"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-blue-600 hover:underline"
                  >
                    PayMongo Dashboard
                  </a>
                </p>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Logs Tab */}
          <TabsContent value="logs">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Activity className="h-5 w-5" />
                  Integration Logs
                </CardTitle>
                <CardDescription>
                  Recent API requests and webhook events
                </CardDescription>
              </CardHeader>
              <CardContent>
                {logs.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    No logs available
                  </div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Time</TableHead>
                        <TableHead>Event Type</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Endpoint</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>User</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {logs.map((log) => (
                        <TableRow key={log.id}>
                          <TableCell className="font-mono text-xs">
                            {log.created_at}
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline">{log.event_type}</Badge>
                          </TableCell>
                          <TableCell className="font-mono text-sm">
                            {log.method || '-'}
                          </TableCell>
                          <TableCell className="font-mono text-xs">
                            {log.endpoint || '-'}
                          </TableCell>
                          <TableCell>
                            {getStatusBadge(log.status_code)}
                          </TableCell>
                          <TableCell>{log.user_name || 'System'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
```

**Files to Create:**
- `resources/js/pages/Admin/Integrations/PayMongo/Index.tsx`

**Verification:**
- ✅ Secure credential management (masked display)
- ✅ Test connection functionality
- ✅ Live/Test mode toggle
- ✅ Enable/Disable toggle
- ✅ Webhook URL display with copy button
- ✅ Integration logs table
- ✅ Responsive design

---

## Phase 5: Permissions & Security

**Duration:** 0.5 days

### Task 5.1: Create Permissions

**Goal:** Set up RBAC permissions for PayMongo configuration.

**Implementation Steps:**

1. **Create Permissions Seeder:**

Create file: `database/seeders/PayMongoPermissionsSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PayMongoPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'payments.paymongo.configure' => 'Configure PayMongo API credentials',
            'payments.paymongo.view_logs' => 'View PayMongo integration logs',
            'payments.paymongo.test' => 'Test PayMongo API connection',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web', 'description' => $description]
            );
        }

        // Assign permissions to Superadmin role
        $superadmin = Role::where('name', 'Superadmin')->first();
        
        if ($superadmin) {
            $superadmin->givePermissionTo(array_keys($permissions));
        }
    }
}
```

2. **Run Seeder:**
   ```bash
   php artisan db:seed --class=PayMongoPermissionsSeeder
   ```

**Files to Create:**
- `database/seeders/PayMongoPermissionsSeeder.php`

---

### Task 5.2: Add Middleware to Controller

**Goal:** Ensure only Superadmin can access PayMongo configuration.

**Implementation Steps:**

Update the controller constructor to include permission checks:

```php
public function __construct(PayMongoService $payMongoService)
{
    $this->middleware(['auth', 'role:Superadmin']);
    $this->middleware('permission:payments.paymongo.configure')->except(['index', 'logs']);
    $this->middleware('permission:payments.paymongo.view_logs')->only(['index', 'logs']);
    $this->middleware('permission:payments.paymongo.test')->only(['testConnection']);
    
    $this->payMongoService = $payMongoService;
}
```

**Verification:**
- ✅ Only Superadmin can access PayMongo configuration
- ✅ Permissions properly assigned
- ✅ Middleware enforces access control

---

## Phase 6: Testing & Documentation

**Duration:** 0.5 days

### Task 6.1: Manual Testing Checklist

**Configuration:**
- ✅ Can save public key
- ✅ Can save secret key (encrypted in database)
- ✅ Can save webhook secret (encrypted in database)
- ✅ Can toggle live/test mode
- ✅ Can enable/disable integration
- ✅ Credentials are masked in UI
- ✅ Secret keys can be updated without displaying current value

**Connection Test:**
- ✅ Test connection works with valid credentials
- ✅ Test connection fails with invalid credentials
- ✅ Test result is logged
- ✅ Last test status is saved

**Webhooks:**
- ✅ Webhook URL is generated correctly
- ✅ Webhook signature verification works
- ✅ Webhook events are logged
- ✅ Webhook events are processed

**Security:**
- ✅ Only Superadmin can access configuration page
- ✅ Secret keys are encrypted in database
- ✅ Configuration changes are audit logged
- ✅ Non-superadmin users get 403 forbidden

**Logs:**
- ✅ API requests are logged
- ✅ Webhook events are logged
- ✅ Connection tests are logged
- ✅ Logs display correctly in UI

---

### Task 6.2: Documentation

**Goal:** Document PayMongo integration for Superadmin.

**Files to Update:**

Add to project documentation:

```markdown
# PayMongo Integration - Superadmin Guide

## Overview
PayMongo integration allows the system to process payments via credit/debit cards, GCash, Maya, and bank transfers.

## Initial Setup (Superadmin Only)

### 1. Get PayMongo API Keys
1. Sign up at https://dashboard.paymongo.com
2. Navigate to Developers > API Keys
3. Copy your **Public Key** and **Secret Key**

### 2. Configure in System
1. Navigate to System Settings > Integrations > PayMongo
2. Enter your Public Key: `pk_test_xxxxxxxxxx`
3. Enter your Secret Key: `sk_test_xxxxxxxxxx`
4. Keep "Test Mode" enabled for initial testing
5. Click "Save Configuration"

### 3. Test Connection
1. Click "Test Connection" button
2. Verify success message appears
3. Check "Integration Logs" tab for details

### 4. Configure Webhooks
1. Copy the Webhook URL from the system
2. Go to PayMongo Dashboard > Webhooks
3. Add new webhook with the copied URL
4. Copy the Webhook Secret and paste it in the system

### 5. Go Live
1. Once testing is complete, get your **live API keys** from PayMongo
2. Update Public Key and Secret Key with live versions
3. Toggle "Live Mode" to ON
4. Enable "Enable PayMongo" toggle
5. Save configuration

## Security Notes
- API keys are encrypted in the database
- Only Superadmin can access configuration
- All configuration changes are audit logged
- Webhook signatures are verified for security

## Troubleshooting
- If connection test fails, verify API keys are correct
- Check that keys match the mode (test/live)
- Review Integration Logs for detailed error messages
```

---

## Summary

### Implementation Breakdown

| Phase | Duration | Tasks | Status |
|-------|----------|-------|--------|
| **Phase 1** | 0.5 days | Database Schema & Models | ⏳ Pending |
| **Phase 2** | 1 day | PayMongo Service Layer | ⏳ Pending |
| **Phase 3** | 0.75 days | Backend Controller & Routes | ⏳ Pending |
| **Phase 4** | 1.5 days | Frontend Configuration Page | ⏳ Pending |
| **Phase 5** | 0.5 days | Permissions & Security | ⏳ Pending |
| **Phase 6** | 0.5 days | Testing & Documentation | ⏳ Pending |
| **Total** | **4.75 days** | 12 tasks | ⏳ Not Started |

### Key Files Summary

**Files to Create (11):**
1. `database/migrations/YYYY_MM_DD_create_paymongo_configurations_table.php`
2. `app/Models/PayMongoConfiguration.php`
3. `app/Models/PayMongoLog.php`
4. `app/Models/PayMongoWebhook.php`
5. `app/Services/PayMongoService.php`
6. `app/Http/Controllers/Admin/PayMongoConfigurationController.php`
7. `app/Http/Controllers/Webhooks/PayMongoWebhookController.php`
8. `resources/js/pages/Admin/Integrations/PayMongo/Index.tsx`
9. `database/seeders/PayMongoPermissionsSeeder.php`

**Files to Modify (2):**
1. `routes/admin.php` - Add PayMongo configuration routes
2. `routes/web.php` - Add webhook route

### Success Criteria

✅ Superadmin can configure PayMongo API credentials  
✅ API keys are encrypted in database  
✅ Test connection functionality works  
✅ Live/Test mode toggle works  
✅ Webhook endpoint receives events  
✅ Webhook signature verification works  
✅ Integration logs are viewable  
✅ Only Superadmin has access  
✅ Configuration changes are audit logged  
✅ UI displays masked credentials  

---

## Quick Start Commands

```bash
# Phase 1: Create Migration & Models
php artisan make:migration create_paymongo_configurations_table
php artisan make:model PayMongoConfiguration
php artisan make:model PayMongoLog
php artisan make:model PayMongoWebhook
php artisan migrate

# Phase 2: Install Guzzle
composer require guzzlehttp/guzzle

# Phase 3: Create Controllers
php artisan make:controller Admin/PayMongoConfigurationController
php artisan make:controller Webhooks/PayMongoWebhookController

# Phase 4: Create Frontend Directory
mkdir -p resources/js/pages/Admin/Integrations/PayMongo

# Phase 5: Seed Permissions
php artisan make:seeder PayMongoPermissionsSeeder
php artisan db:seed --class=PayMongoPermissionsSeeder

# Build Frontend
npm run build

# Clear Caches
php artisan optimize:clear
```

---

**End of Implementation Plan**
