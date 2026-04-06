import { spawn, spawnSync } from 'node:child_process';
import process from 'node:process';

const rootEnv = {
    ...process.env,
    APP_ENV: 'testing',
    APP_URL: 'http://127.0.0.1:8011',
    APP_KEY: 'base64:2fl+Ktvkfl+Fuz4Qk/NaWvy1Pt7EQqHMA2wc/ulkZyo=',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'file',
    DB_CONNECTION: 'mysql',
    DB_HOST: process.env.E2E_DB_HOST || '127.0.0.1',
    DB_PORT: process.env.E2E_DB_PORT || '3306',
    DB_DATABASE: process.env.E2E_DB_DATABASE || 'conecta_hn_e2e_testing',
    DB_USERNAME: process.env.E2E_DB_USERNAME || 'root',
    DB_PASSWORD: process.env.E2E_DB_PASSWORD || '',
};

function runStep(command, args) {
    console.error(`\n[e2e-server] Running: ${command} ${args.join(' ')}`);

    const result = spawnSync(command, args, {
        stdio: 'inherit',
        env: rootEnv,
    });

    if (result.status !== 0) {
        console.error(`[e2e-server] Step failed with exit code ${result.status ?? 1}`);
        process.exit(result.status ?? 1);
    }
}

runStep('php', ['artisan', 'optimize:clear', '--env=testing']);
runStep('php', ['artisan', 'migrate:fresh', '--env=testing', '--force']);
runStep('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\GeoSeeder', '--env=testing', '--force']);
runStep('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\E2EStorefrontSeeder', '--env=testing', '--force']);

console.error('\n[e2e-server] Starting Laravel server on http://127.0.0.1:8011');
const server = spawn('php', ['artisan', 'serve', '--env=testing', '--host=127.0.0.1', '--port=8011'], {
    stdio: 'inherit',
    env: rootEnv,
});

const forwardSignal = (signal) => {
    if (!server.killed) {
        server.kill(signal);
    }
};

process.on('SIGINT', () => forwardSignal('SIGINT'));
process.on('SIGTERM', () => forwardSignal('SIGTERM'));

server.on('exit', (code) => {
    process.exit(code ?? 0);
});
