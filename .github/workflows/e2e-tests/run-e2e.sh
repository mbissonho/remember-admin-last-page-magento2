#!/bin/bash
cd /tmp/module/e2e
rm -rf package-lock.json
npm i
npx playwright install --with-deps
npx playwright test -c playwright-ci.config.js
