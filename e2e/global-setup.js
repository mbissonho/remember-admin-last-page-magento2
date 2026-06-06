import { execSync } from 'node:child_process';

/**
 * Playwright global setup: runs ONCE, before any worker/spec, on the runner's
 * main process. Its only job is to assert that the Magento instance is in the
 * scenario the e2e suite needs and to FAIL THE WHOLE RUN with a clear reason if
 * it is not. It never changes any store configuration.
 *
 * How the Magento CLI is invoked is driven entirely by env vars (set in e2e/.env,
 * which the Playwright config loads via dotenv), so the same file works for a
 * local docker-magento setup, a teammate on Warden and the GitHub Actions flow:
 *
 *   MAGENTO_CLI_IN_CONTAINER   "true" to exec the CLI inside a container, else
 *                              run it directly on the host/runner. Default: false.
 *   MAGENTO_ROOT               Magento root used as the working directory — the
 *                              path INSIDE the container (container mode) or on
 *                              the host (direct mode).
 *   MAGENTO_CLI_CONTAINER      Container name to `docker exec` into (container mode).
 *   MAGENTO_CLI_CONTAINER_USER User to run the CLI as in the container (optional).
 *
 * Docs:
 *  - https://playwright.dev/docs/test-global-setup-teardown
 *  - https://playwright.dev/docs/api/class-testconfig#test-config-global-setup
 */

function isTruthy(value) {
    return /^(1|true|yes|on)$/i.test(String(value || '').trim());
}

/**
 * Builds the execSync command + options for a Magento CLI call from the env vars.
 * Throws (failing the whole run) when required vars are missing.
 */
function buildCliInvocation(args) {
    const root = (process.env.MAGENTO_ROOT || '').trim();

    if (isTruthy(process.env.MAGENTO_CLI_IN_CONTAINER)) {
        const container = (process.env.MAGENTO_CLI_CONTAINER || '').trim();
        if (!container) {
            throw new Error(
                'MAGENTO_CLI_CONTAINER is required when MAGENTO_CLI_IN_CONTAINER=true ' +
                '(set the container name in e2e/.env, e.g. 248community-phpfpm-1).'
            );
        }

        const user = (process.env.MAGENTO_CLI_CONTAINER_USER || '').trim();
        const parts = ['docker', 'exec'];
        if (user) {
            parts.push('-u', user);
        }
        if (root) {
            parts.push('-w', root);
        }
        parts.push(container, 'php', 'bin/magento', args);

        return {
            command: parts.join(' '),
            options: { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
            target: `container "${container}"${user ? ` (user ${user})` : ''}${root ? ` at ${root}` : ''}`,
        };
    }

    // Direct mode: the Magento CLI runs on the host/runner.
    if (!root) {
        throw new Error(
            'MAGENTO_ROOT is required when MAGENTO_CLI_IN_CONTAINER=false ' +
            '(set the Magento path on the host in e2e/.env).'
        );
    }

    return {
        command: `php bin/magento ${args}`,
        options: { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
        target: `host at ${root}`,
    };
}

/** Runs a Magento CLI command and returns its trimmed stdout. */
function magento(args) {
    const { command, options } = buildCliInvocation(args);
    return execSync(command, options).replace(/^Xdebug:.*$/gm, '').trim();
}

/** Human-readable description of where/how the CLI is being run. */
function cliTarget() {
    try {
        return buildCliInvocation('--version').target;
    } catch (error) {
        return asOneLine(error);
    }
}

function asOneLine(error) {
    return String(error && error.message ? error.message : error)
        .replace(/\s+/g, ' ')
        .trim();
}

/** Store-config values that must match exactly for the suite to be meaningful. */
const CONFIG_REQUIREMENTS = [
    {
        path: 'admin/mbissonho_remember_admin_last_page/active',
        expected: '1',
        why: 'the "remember last page" feature must be active',
    },
    {
        path: 'admin/mbissonho_remember_admin_last_page/active_notification_message',
        expected: '1',
        why: 'without it the #toast-container is not rendered on the login page and the notification never initializes',
    },
    {
        path: 'admin/security/admin_account_sharing',
        expected: '1',
        why: 'parallel tests sign the same admin in concurrently; with 0 the sessions invalidate each other',
    },
];

/** Module on/off states the suite relies on. */
const MODULE_REQUIREMENTS = [
    {
        module: 'Mbissonho_RememberAdminLastPage',
        want: 'enabled',
        why: 'it is the module under test',
    },
    {
        module: 'Magento_TwoFactorAuth',
        want: 'disabled',
        why: '2FA blocks the automated username/password login',
    },
    {
        module: 'Magento_AdminAnalytics',
        want: 'disabled',
        why: 'its welcome popup overlays the screen and gets in the way of clicks',
    },
];

const SESSION_LIFETIME_MAX = 60;

function moduleState(rawOutput) {
    const out = rawOutput.toLowerCase();
    if (out.includes('is disabled')) {
        return 'disabled';
    }
    if (out.includes('is enabled')) {
        return 'enabled';
    }
    // A module that is not installed at all cannot interfere with the suite, so
    // report it as a distinct "absent" state. The caller treats absent as
    // satisfying a "disabled" requirement (e.g. Magento_TwoFactorAuth may simply
    // not be present in the build under test).
    if (out.includes('module does not exist')) {
        return 'absent';
    }
    return `unknown ("${rawOutput}")`;
}

/** True when the actual module state satisfies the required one. */
function moduleStateSatisfies(state, want) {
    if (state === want) {
        return true;
    }
    // An absent module behaves like a disabled one for our purposes: it is not
    // loaded and cannot block login or render any overlay.
    return want === 'disabled' && state === 'absent';
}

export default async function globalSetup() {
    const problems = [];

    for (const req of CONFIG_REQUIREMENTS) {
        try {
            const value = magento(`config:show ${req.path}`);
            if (value !== req.expected) {
                problems.push(`${req.path} = "${value}" (expected "${req.expected}") — ${req.why}`);
            }
        } catch (error) {
            problems.push(`failed to read ${req.path}: ${asOneLine(error)}`);
        }
    }

    // The session-expiration test (module.spec.js) waits ~65s for the admin
    // session to die, so the lifetime must be short.
    try {
        const raw = magento('config:show admin/security/session_lifetime');
        const seconds = Number.parseInt(raw, 10);
        if (!Number.isFinite(seconds) || seconds <= 0 || seconds > SESSION_LIFETIME_MAX) {
            problems.push(
                `admin/security/session_lifetime = "${raw}" (expected an integer between 1 and ${SESSION_LIFETIME_MAX}) ` +
                '— the session-expiration test waits ~65s'
            );
        }
    } catch (error) {
        problems.push(`failed to read admin/security/session_lifetime: ${asOneLine(error)}`);
    }

    for (const req of MODULE_REQUIREMENTS) {
        try {
            const state = moduleState(magento(`module:status ${req.module}`));
            if (!moduleStateSatisfies(state, req.want)) {
                problems.push(`module ${req.module} is "${state}" (expected "${req.want}") — ${req.why}`);
            }
        } catch (error) {
            problems.push(`failed to check module ${req.module}: ${asOneLine(error)}`);
        }
    }

    if (problems.length > 0) {
        const list = problems.map((problem, index) => `  ${index + 1}. ${problem}`).join('\n');
        throw new Error(
            '\n' +
            '────────────────────────────────────────────────────────────\n' +
            ' Inadequate e2e scenario — run aborted (no configuration was changed).\n' +
            ' Fix the items below on the Magento instance and run again:\n\n' +
            list + '\n\n' +
            ` (CLI checked via: ${cliTarget()})\n` +
            '────────────────────────────────────────────────────────────\n'
        );
    }
}
