<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Models\Appointment;
use App\Models\Coupon;
use App\Models\CrmDeal;
use App\Models\CustomerNote;
use App\Models\CustomerTask;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PaymentMorphMapRatchetTest extends TestCase
{
    #[Test]
    public function canonical_morph_inventory_includes_the_exact_payment_alias(): void
    {
        $expected = [
            'user' => User::class,
            'tenant' => Tenant::class,
            'product' => Product::class,
            'order' => Order::class,
            'appointment' => Appointment::class,
            'quote' => Quote::class,
            'coupon' => Coupon::class,
            'crm_deal' => CrmDeal::class,
            'customer_note' => CustomerNote::class,
            'customer_task' => CustomerTask::class,
            'service' => Service::class,
            'service_provider' => ServiceProviderModel::class,
            'payment' => Payment::class,
        ];

        self::assertSame($expected, Relation::morphMap());
        self::assertSame(Payment::class, Relation::getMorphedModel('payment'));
        self::assertSame('payment', (new Payment)->getMorphClass());

        $root = dirname(__DIR__, 2);
        self::assertPaymentMorphMapSource(
            (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php'),
        );
    }

    #[Test]
    public function payment_morph_ratchet_rejects_missing_wrong_alias_and_wrong_class(): void
    {
        $fixtures = [
            [
                "<?php Relation::enforceMorphMap(['user' => User::class]);",
                'Payment morph alias must be present.',
            ],
            [
                "<?php Relation::enforceMorphMap(['user' => User::class, 'subscription_payment' => Payment::class]);",
                'Payment morph alias must be exactly payment.',
            ],
            [
                "<?php Relation::enforceMorphMap(['user' => User::class, 'payment' => Invoice::class]);",
                'Payment morph alias must resolve to App\\Models\\Payment.',
            ],
        ];

        $failures = [];
        foreach ($fixtures as [$source]) {
            try {
                self::assertPaymentMorphMapSource($source);
                $failures[] = 'An invalid payment morph map unexpectedly satisfied the ratchet.';
            } catch (AssertionFailedError $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        self::assertSame(array_column($fixtures, 1), $failures);
    }

    private static function assertPaymentMorphMapSource(string $source): void
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $calls = (new NodeFinder)->find(
            $statements,
            static fn (Node $node): bool => $node instanceof StaticCall
                && $node->class instanceof Name
                && $node->class->getLast() === 'Relation'
                && $node->name instanceof Identifier
                && $node->name->toString() === 'enforceMorphMap',
        );
        self::assertCount(1, $calls, 'Exactly one enforced morph map is required.');
        self::assertCount(1, $calls[0]->args);
        self::assertInstanceOf(Array_::class, $calls[0]->args[0]->value);

        $aliases = [];
        foreach ($calls[0]->args[0]->value->items as $item) {
            self::assertNotNull($item);
            self::assertInstanceOf(String_::class, $item->key);
            self::assertInstanceOf(ClassConstFetch::class, $item->value);
            self::assertInstanceOf(Name::class, $item->value->class);
            $aliases[$item->key->value] = $item->value->class->toString();
        }

        $paymentAliases = array_keys(array_filter(
            $aliases,
            static fn (string $class): bool => $class === 'Payment',
        ));
        if ($paymentAliases !== [] && $paymentAliases !== ['payment']) {
            self::fail('Payment morph alias must be exactly payment.');
        }
        if (! array_key_exists('payment', $aliases)) {
            self::fail('Payment morph alias must be present.');
        }
        if ($aliases['payment'] !== 'Payment') {
            self::fail('Payment morph alias must resolve to App\Models\Payment.');
        }
    }
}
