import { copyFileSync, mkdirSync } from 'node:fs'

mkdirSync('dist', { recursive: true })
copyFileSync('browser/js/wc-recurring-admin.js', 'dist/wc-recurring-admin.js')
copyFileSync('browser/style/wc-recurring.css', 'dist/wc-recurring.css')
