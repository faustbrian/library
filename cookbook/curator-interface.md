# Curator Interface

The `Curator` interface allows non-Eloquent objects to own media files.

## Why Use Curator?

Use cases:
- DTOs or value objects that need file attachments
- External API resources that store files locally
- Aggregates in DDD that aren't Eloquent models
- Multi-tenancy where tenant isn't a model

## Implementation

```php
use Cline\Archive\Contracts\Curator;

class ExternalResource implements Curator
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
    ) {}

    public function getCuratorId(): string
    {
        return $this->id;
    }

    public function getCuratorType(): string
    {
        return $this->type;
    }
}
```

## Usage

```php
$resource = new ExternalResource('api-user-123', 'external_users');

$media = Archive::add($file)
    ->toCurator($resource)
    ->toCollection('avatars')
    ->store();

// Later retrieval
$media = Media::where('curator_id', 'api-user-123')
    ->where('curator_type', 'external_users')
    ->where('collection', 'avatars')
    ->first();
```

## Complete Examples

### Third-Party API Integration

```php
class StripeCustomer implements Curator
{
    public function __construct(
        public readonly string $stripeId,
    ) {}

    public function getCuratorId(): string
    {
        return $this->stripeId;
    }

    public function getCuratorType(): string
    {
        return 'stripe_customers';
    }

    public function attachInvoice(UploadedFile $invoice): Media
    {
        return Archive::add($invoice)
            ->toCurator($this)
            ->toCollection('invoices')
            ->withProperties(['stripe_id' => $this->stripeId])
            ->toDisk('s3-invoices')
            ->store();
    }

    public function getInvoices()
    {
        return Media::where('curator_id', $this->stripeId)
            ->where('curator_type', 'stripe_customers')
            ->where('collection', 'invoices')
            ->get();
    }
}

// Usage
$customer = new StripeCustomer('cus_123456');
$customer->attachInvoice($pdfFile);
```

### Multi-Tenant Context

```php
class Tenant implements Curator
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $slug,
    ) {}

    public function getCuratorId(): string
    {
        return $this->uuid;
    }

    public function getCuratorType(): string
    {
        return 'tenants';
    }

    public function uploadBrandingAsset(
        UploadedFile $file,
        string $assetType
    ): Media {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection("branding.{$assetType}")
            ->withProperties([
                'tenant_slug' => $this->slug,
                'asset_type' => $assetType,
            ])
            ->store();
    }

    public function getLogo(): ?Media
    {
        return Media::where('curator_id', $this->uuid)
            ->where('curator_type', 'tenants')
            ->where('collection', 'branding.logo')
            ->first();
    }
}

// Usage
$tenant = new Tenant('550e8400-e29b-41d4-a716-446655440000', 'acme-corp');
$tenant->uploadBrandingAsset($logoFile, 'logo');
$logo = $tenant->getLogo();
```

### DDD Aggregate

```php
class OrderAggregate implements Curator
{
    public function __construct(
        public readonly OrderId $orderId,
    ) {}

    public function getCuratorId(): string
    {
        return $this->orderId->toString();
    }

    public function getCuratorType(): string
    {
        return 'orders';
    }

    public function attachReceipt(string $pdfPath): Media
    {
        return Archive::add($pdfPath)
            ->toCurator($this)
            ->toCollection('receipts')
            ->withFileName("receipt-{$this->orderId}.pdf")
            ->preservingOriginal()
            ->store();
    }

    public function attachSignedContract(UploadedFile $contract): Media
    {
        return Archive::add($contract)
            ->toCurator($this)
            ->toCollection('contracts')
            ->withProperties([
                'signed_at' => now()->toIso8601String(),
                'order_id' => $this->orderId->toString(),
            ])
            ->store();
    }
}
```

## Eloquent Models with Curator

You can implement both the trait AND the interface:

```php
class User extends Model implements Curator
{
    use HasCrates;

    public function getCuratorId(): string
    {
        return $this->uuid; // Use UUID instead of auto-increment ID
    }

    public function getCuratorType(): string
    {
        return 'app_users'; // Custom type instead of morph class
    }
}
```

This allows you to:
- Use custom IDs (UUIDs, ULIDs, etc.)
- Control the morph type value
- Maintain consistency across different storage systems
