import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(scriptDir, '..');
const viteManifestPath = path.join(projectRoot, 'public', 'build', 'manifest.json');

const rootEnv = {
    ...process.env,
    APP_ENV: 'testing',
    APP_URL: 'http://127.0.0.1:8011',
    APP_KEY: 'base64:2fl+Ktvkfl+Fuz4Qk/NaWvy1Pt7EQqHMA2wc/ulkZyo=',
    CACHE_STORE: 'array',
    BROADCAST_CONNECTION: 'null',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    SESSION_DOMAIN: '',
    SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array',
    PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false',
    ACTIVITY_LOGGER_ENABLED: 'false',
    E2E_BROWSER_RELEASE: 'true',
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
        encoding: 'utf8',
        env: rootEnv,
        cwd: projectRoot,
    });

    if (result.error) {
        console.error(`[e2e-server] ${command} failed to start: ${result.error.message}`);
        process.exit(1);
    }

    if (result.status !== 0) {
        if (result.stdout) {
            process.stderr.write(result.stdout);
        }

        if (result.stderr) {
            process.stderr.write(result.stderr);
        }

        console.error(`[e2e-server] Step failed with exit code ${result.status ?? 1}`);
        process.exit(result.status ?? 1);
    }

    if (result.stdout) {
        process.stderr.write(result.stdout);
    }

    if (result.stderr) {
        process.stderr.write(result.stderr);
    }
}

function runNpmBuildStep() {
    if (process.platform === 'win32') {
        // Use cmd /c for maximum compatibility inside Playwright webServer child processes.
        runStep('cmd', ['/c', 'npm run build']);

        return;
    }

    runStep('npm', ['run', 'build']);
}

runStep('php', ['artisan', 'optimize:clear']);

if (process.env.E2E_SKIP_BUILD !== '1' && !fs.existsSync(viteManifestPath)) {
    runNpmBuildStep();
} else if (!fs.existsSync(viteManifestPath)) {
    console.error('\n[e2e-server] Skipping frontend build because E2E_SKIP_BUILD=1');
}

if (process.env.E2E_SKIP_BOOTSTRAP !== '1') {
    runStep('php', ['artisan', 'migrate:fresh', '--force']);
    runStep('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\GeoSeeder', '--force']);
    runStep('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\E2EStorefrontSeeder', '--force']);
} else {
    console.error('\n[e2e-server] Skipping DB bootstrap because E2E_SKIP_BOOTSTRAP=1');
}

let server = null;
let shuttingDown = false;

function startLaravelServer() {
    console.error('\n[e2e-server] Starting Laravel server on http://127.0.0.1:8011');

    server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8011', '--no-reload'], {
        stdio: 'inherit',
        env: rootEnv,
        cwd: projectRoot,
    });

    server.on('exit', (code) => {
        if (shuttingDown) {
            process.exit(code ?? 0);
        }

        console.error(`[e2e-server] Laravel server exited unexpectedly with code ${code ?? 'unknown'}. Restarting in 1s...`);
        setTimeout(startLaravelServer, 1000);
    });
}

const forwardSignal = (signal) => {
    shuttingDown = true;

    if (server && !server.killed) {
        server.kill(signal);
    } else {
        process.exit(0);
    }
};

process.on('SIGINT', () => forwardSignal('SIGINT'));
process.on('SIGTERM', () => forwardSignal('SIGTERM'));

startLaravelServer();
