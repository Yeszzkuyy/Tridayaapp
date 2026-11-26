<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

/**
 * Concurrency test khusus PostgreSQL.
 *
 * Tidak dijalankan pada suite SQLite biasa. Jalankan dengan database
 * development/staging yang terpisah dari production:
 *
 * WS1A_CONCURRENCY_TEST=1 \
 * WS1A_PG_DATABASE=tridayadb_preview \
 * WS1A_PG_SCHEMA=ws1a_verify \
 * php artisan test tests/Feature/CustomerDeletionConcurrencyTest.php
 */
class CustomerDeletionConcurrencyTest extends TestCase
{
    private ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('WS1A_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set WS1A_CONCURRENCY_TEST=1 untuk menjalankan test PostgreSQL ini.');
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open tidak tersedia untuk simulasi dua koneksi.');
        }
    }

    public function test_insert_transaksi_baru_tidak_bisa_melewati_soft_delete_customer(): void
    {
        $database = (string) getenv('WS1A_PG_DATABASE');
        $schema = (string) getenv('WS1A_PG_SCHEMA');

        if ($database === '' || $schema === '') {
            $this->markTestSkipped('WS1A_PG_DATABASE dan WS1A_PG_SCHEMA wajib diisi.');
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $schema)) {
            $this->fail('Nama schema hanya boleh huruf, angka, dan underscore.');
        }

        $appDatabase = (string) config('database.connections.pgsql.database');
        if ($database === $appDatabase) {
            $this->fail('Test ini tidak boleh dijalankan pada database aplikasi utama.');
        }

        $this->connection = $this->pdo($database);
        $this->connection->exec('SET search_path TO "'.$schema.'"');

        $suffix = bin2hex(random_bytes(6));
        $customerName = 'WS1A Concurrent '.$suffix;
        $invoiceNumber = 'WS1A-CONC-'.$suffix;

        $this->connection->prepare(
            'INSERT INTO customers (name, status, created_at, updated_at) VALUES (?, ?, now(), now())'
        )->execute([$customerName, 'deal']);

        $customerId = (int) $this->connection
            ->query('SELECT id FROM customers WHERE name = '.$this->connection->quote($customerName))
            ->fetchColumn();

        $this->assertGreaterThan(0, $customerId);

        // Transaksi A: kunci baris customer dan set deleted_at, belum commit.
        $this->connection->beginTransaction();
        $this->connection->prepare('SELECT id FROM customers WHERE id = ? FOR UPDATE')
            ->execute([$customerId]);
        $this->connection->prepare('UPDATE customers SET deleted_at = now() WHERE id = ?')
            ->execute([$customerId]);

        $script = $this->writeChildScript();
        $config = config('database.connections.pgsql');
        $env = [
            'WS1A_HOST' => (string) $config['host'],
            'WS1A_PORT' => (string) $config['port'],
            'WS1A_DATABASE' => $database,
            'WS1A_SCHEMA' => $schema,
            'WS1A_USERNAME' => (string) $config['username'],
            'WS1A_PASSWORD' => (string) $config['password'],
            'WS1A_CUSTOMER_ID' => (string) $customerId,
            'WS1A_INVOICE_NUMBER' => $invoiceNumber,
        ];

        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );

        $this->assertIsResource($process);

        // Beri waktu anak masuk ke trigger dan menunggu lock, lalu commit A.
        usleep(500000);
        $this->connection->commit();

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($script);

        $output = trim($stdout."\n".$stderr);

        $this->assertSame(0, $exitCode, 'Insert seharusnya ditolak trigger, bukan berhasil. Output: '.$output);
        $this->assertStringContainsString('is in Trash and cannot receive new transactions', $output);

        $this->assertSame(
            0,
            (int) $this->connection
                ->query('SELECT COUNT(*) FROM invoices WHERE invoice_number = '.$this->connection->quote($invoiceNumber))
                ->fetchColumn()
        );

        $this->connection->prepare('DELETE FROM customers WHERE id = ?')->execute([$customerId]);
    }

    protected function tearDown(): void
    {
        if ($this->connection?->inTransaction()) {
            $this->connection->rollBack();
        }

        $this->connection = null;

        parent::tearDown();
    }

    private function pdo(string $database): PDO
    {
        $config = config('database.connections.pgsql');

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $database),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function writeChildScript(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ws1a-child-');

        $code = <<<'PHP'
<?php
$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('WS1A_HOST'), getenv('WS1A_PORT'), getenv('WS1A_DATABASE')),
    getenv('WS1A_USERNAME'),
    getenv('WS1A_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('SET search_path TO "'.getenv('WS1A_SCHEMA').'"');
$pdo->exec("SET statement_timeout = '5s'");

try {
    $pdo->prepare(
        'INSERT INTO invoices '
        .'(invoice_number, customer_id, amount, status, issue_date, created_at, updated_at) '
        .'VALUES (?, ?, 1000, ?, current_date, now(), now())'
    )->execute([getenv('WS1A_INVOICE_NUMBER'), (int) getenv('WS1A_CUSTOMER_ID'), 'unpaid']);

    echo "INSERT_SUCCEEDED\n";
    exit(1);
} catch (PDOException $e) {
    echo $e->getMessage()."\n";
    exit(0);
}
PHP;

        file_put_contents($path, $code);

        return $path;
    }
}
