<script setup>
import { reactive, ref } from 'vue'
import settingsMixin from './js/settings.js'

defineOptions({
    mixins: [settingsMixin]
})

const dataElement = document.getElementById('wc-recurring-settings-data')
const config = dataElement ? JSON.parse(dataElement.textContent) : { options: {}, subscriptions: {}, endpoints: {}, strings: {} }
const options = reactive({ ...config.options })
const tab = ref('general')
const mediaTitle = ref('')

const businessFlags = [
    ['wc_pdf_xinvoice', 'Enable XInvoice', 'Enable support for XInvoice (XML)'],
    ['wc_order_show_completed', 'Order account filter', 'Only show completed WooCommerce orders on customer account pages'],
    ['wc_customer_login_gdpr', 'Customer GDPR compliance', 'Provide a GDPR acceptance checkbox on customer login'],
    ['wc_pdf_b2c', 'Enable B2C', 'Create invoices for business-to-customer relationships']
]
const schedulerFlags = [
    ['wc_recur_test', 'Test Mode', 'Replace all recipients with the administrator email address'],
    ['wc_payment_reminder', 'Payment report', 'Send a daily report of unpaid invoices to the administrator'],
    ['wc_recur', 'Automate invoice submission', 'Submit recurring invoices to customers on a daily schedule'],
    ['wc_recur_reminder', 'Payment reminder', 'Send payment reminders when an invoice is due']
]
const placeholders = [
    ['{COMPANY_NAME}', 'Company name'], ['{ADDRESS}', 'Street and number'], ['{POSTCODE}', 'Postal code'],
    ['{CITY}', 'City'], ['{EMAIL}', 'Contact email'], ['{VAT_ID}', 'VAT ID'], ['{IBAN}', 'IBAN'],
    ['{BIC}', 'BIC'], ['{BANK_NAME}', 'Bank name'], ['{INVOICE_NUMBER}', 'Invoice number'],
    ['{INVOICE_CREATED}', 'Invoice creation date'], ['{DUE_DAYS}', 'Due period in days'], ['{DUE_DATE}', 'Due date']
]

function endpoint(name) {
    return config.endpoints[name] || {}
}

function clearMedia() {
    options.wc_pdf_template = ''
    mediaTitle.value = ''
}

function runTask(event, name) {
    settingsMixin.methods.runTask(event.currentTarget, name)
}

function selectMedia() {
    settingsMixin.methods.openMedia((attachment) => {
        options.wc_pdf_template = attachment.id
        mediaTitle.value = attachment.title
    })
}
</script>

<template>
    <div class="wc-recurring-vue">
        <v-alert v-if="config.scheduleActive" type="info" variant="tonal" class="mb-4">The schedule is properly
            installed and running</v-alert>
        <v-alert v-else type="error" variant="tonal" class="mb-4">The scheduled task is not installed. Please try to
            reenable the plugin.</v-alert>

        <v-tabs v-model="tab" color="primary" grow>
            <v-tab value="general">General</v-tab>
            <v-tab value="invoice">Invoice template</v-tab>
            <v-tab value="email">Email templates</v-tab>
            <v-tab value="export">Export</v-tab>
        </v-tabs>

        <v-window v-model="tab" class="pt-4">
            <v-window-item value="general">
                <form method="post" :action="endpoint('general').url">
                    <input type="hidden" name="_wpnonce" :value="endpoint('general').nonce" />
                    <v-row>
                        <v-col cols="12" md="7">
                            <v-card variant="outlined"><v-card-title>Business</v-card-title><v-card-text>
                                    <v-text-field v-model="options.wc_company_name" name="wc_company_name"
                                        label="Company name" variant="outlined" />
                                    <v-text-field v-model="options.wc_company_email" name="wc_company_email"
                                        label="Contact email" type="email" variant="outlined" />
                                    <v-text-field v-model="options.wc_company_vat" name="wc_company_vat" label="VAT ID"
                                        variant="outlined" />
                                    <v-text-field v-model="options.wc_invoice_due_days" name="wc_invoice_due_days"
                                        label="Due date in days" type="number" variant="outlined" />
                                    <v-select v-model="options.wc_order_subscriptions" name="wc_order_subscriptions"
                                        label="Subscription option"
                                        :items="[{ title: 'Customer choose', value: '' }, ...Object.entries(config.subscriptions).map(([value, title]) => ({ title, value }))]"
                                        item-title="title" item-value="value" variant="outlined" />
                                    <div class="text-body-2 mb-4">{{ config.companyAddress }} <a
                                            :href="config.woocommerceSettingsUrl">WooCommerce settings</a></div>
                                    <input v-for="field in businessFlags" :key="field[0] + '-hidden'" type="hidden"
                                        :name="field[0]" value="0" />
                                    <v-checkbox v-for="field in businessFlags" :key="field[0]"
                                        v-model="options[field[0]]" :label="field[1] + ' - ' + field[2]"
                                        :name="field[0]" value="1" hide-details />
                                </v-card-text></v-card>
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-card variant="outlined" class="mb-4"><v-card-title>Email
                                    details</v-card-title><v-card-text><v-text-field v-model="options.wc_mail_reminder"
                                        name="wc_mail_reminder" label="Report recipient" type="email"
                                        variant="outlined" /><v-text-field v-model="options.wc_mail_sender"
                                        name="wc_mail_sender" label="Sender address"
                                        variant="outlined" /></v-card-text></v-card>
                            <v-card variant="outlined"><v-card-title>Task Scheduler</v-card-title><v-card-text>
                                    <input v-for="field in schedulerFlags" :key="field[0] + '-hidden'" type="hidden"
                                        :name="field[0]" value="0" />
                                    <v-checkbox v-for="field in schedulerFlags" :key="field[0]"
                                        v-model="options[field[0]]" :label="field[1] + ' - ' + field[2]"
                                        :name="field[0]" value="1" hide-details />
                                    <v-text-field v-model="options.wc_recur_reminder_age" name="wc_recur_reminder_age"
                                        label="First reminder (days)" type="number" variant="outlined" />
                                    <v-text-field v-model="options.wc_recur_reminder_interval"
                                        name="wc_recur_reminder_interval" label="Reminder interval" type="number"
                                        variant="outlined" />
                                    <v-text-field v-model="options.wc_recur_reminder_max" name="wc_recur_reminder_max"
                                        label="Max reminders" type="number" variant="outlined" />
                                </v-card-text></v-card>
                        </v-col>
                    </v-row>
                    <v-card variant="outlined" class="mt-4"><v-card-title>Manual tasks</v-card-title><v-card-text
                            class="d-flex flex-wrap ga-2"><v-btn
                                v-for="task in [['notify', 'Payment notification'], ['recur', 'Generate invoices'], ['submit', 'Submit invoices'], ['reminder', 'Trigger reminder']]"
                                :key="task[0]" type="button" @click="runTask($event, task[0])">{{ task[1]
                                }}</v-btn></v-card-text></v-card>
                    <div class="d-flex justify-end mt-6"><v-btn color="primary" type="submit">{{ config.strings.save
                    }}</v-btn></div>
                </form>
            </v-window-item>

            <v-window-item value="invoice">
                <form method="post" :action="endpoint('invoice').url"><input type="hidden" name="_wpnonce"
                        :value="endpoint('invoice').nonce" /><v-row><v-col cols="12" md="6"><v-card
                                variant="outlined"><v-card-title>Properties</v-card-title><v-card-text>
                                    <v-text-field v-model="options.wc_pdf_title" name="wc_pdf_title"
                                        label="Document title" variant="outlined" />
                                    <div class="d-flex align-center ga-2 mb-4"><v-text-field
                                            :model-value="mediaTitle || options.wc_pdf_template" label="PDF template"
                                            readonly variant="outlined" /><v-btn type="button" @click="selectMedia">{{
                                                config.strings.selectMedia }}</v-btn><v-btn type="button" variant="text"
                                            @click="clearMedia">{{ config.strings.clearMedia }}</v-btn></div>
                                    <input type="hidden" name="wc_pdf_template" :value="options.wc_pdf_template" />
                                    <v-textarea v-model="options.wc_pdf_condition" name="wc_pdf_condition"
                                        label="Payment terms" variant="outlined" />
                                    <v-textarea v-model="options.wc_pdf_condition_offer" name="wc_pdf_condition_offer"
                                        label="Offer terms" variant="outlined" />
                                    <v-textarea v-model="options.wc_pdf_info" name="wc_pdf_info" label="Info block"
                                        variant="outlined" />
                                </v-card-text></v-card></v-col><v-col cols="12" md="6"><v-card
                                variant="outlined"><v-card-title>Placeholders</v-card-title><v-table>
                                    <thead>
                                        <tr>
                                            <th>Placeholder</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in placeholders" :key="item[0]">
                                            <td>{{ item[0] }}</td>
                                            <td>{{ item[1] }}</td>
                                        </tr>
                                    </tbody>
                                </v-table></v-card></v-col></v-row>
                    <div class="d-flex justify-end mt-6"><v-btn color="primary" type="submit">{{ config.strings.save
                    }}</v-btn></div>
                </form>
            </v-window-item>

            <v-window-item value="email">
                <form method="post" :action="endpoint('email').url"><input type="hidden" name="_wpnonce"
                        :value="endpoint('email').nonce" /><v-card variant="outlined"><v-card-title>Email
                            templates</v-card-title><v-card-text><v-textarea v-model="options.wc_payment_message"
                                name="wc_payment_message" label="Payment report" rows="6"
                                variant="outlined" /><v-textarea v-model="options.wc_recur_message"
                                name="wc_recur_message" label="Automate invoice submission" rows="8"
                                variant="outlined" /><v-textarea v-model="options.wc_recur_reminder_message"
                                name="wc_recur_reminder_message" label="Payment reminder" rows="8"
                                variant="outlined" /></v-card-text></v-card>
                    <div class="d-flex justify-end mt-6"><v-btn color="primary" type="submit">{{ config.strings.save
                    }}</v-btn></div>
                </form>
            </v-window-item>
            <v-window-item value="export">
                <form method="post" :action="endpoint('export').url"><input type="hidden" name="_wpnonce"
                        :value="endpoint('export').nonce" /><v-card
                        variant="outlined"><v-card-title>Export</v-card-title><v-card-text>
                            <p>The export feature currently supports GnuCash CSV format.</p><v-text-field
                                v-model="options.wc_export_locale" name="wc_export_locale" label="Locale"
                                variant="outlined" /><v-textarea v-model="options.wc_export_notes"
                                name="wc_export_notes" label="Notes" variant="outlined" /><v-text-field
                                v-model="options.wc_export_account" name="wc_export_account" label="Account name"
                                variant="outlined" /><v-text-field v-model="options.wc_export_account_posted"
                                name="wc_export_account_posted" label="Posted account name"
                                variant="outlined" /><v-text-field v-model="options.wc_export_account_tax"
                                name="wc_export_account_tax" label="Tax account" variant="outlined" />
                        </v-card-text></v-card>
                    <div class="d-flex justify-end mt-6"><v-btn color="primary" type="submit">{{ config.strings.save
                    }}</v-btn></div>
                </form>
            </v-window-item>
        </v-window>
    </div>
</template>

<style scoped>
.wc-recurring-vue {
    max-width: 1280px;
}

.wc-recurring-vue :deep(.v-card-title) {
    font-weight: 600;
}
</style>
