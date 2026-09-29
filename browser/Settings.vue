<script setup>
import { reactive, ref } from 'vue'

const dataElement = document.getElementById('wc-recurring-settings-data')
const config = dataElement ? JSON.parse(dataElement.textContent) : { options: {}, subscriptions: {}, endpoints: {} }
const t = (text) => window.wp?.i18n?.__(text, 'wc-invoice-pdf') || text
const format = (text, ...values) => values.reduce((translated, value, index) => translated.replace(`%${index + 1}$s`, value), t(text))
const options = reactive({ ...config.options })
const tab = ref('general')
const mediaTitle = ref('')
const saving = ref(false)
const saved = ref(false)

const businessFlags = [
    ['wc_pdf_xinvoice', t('Enable XInvoice'), t('Enable support for XInvoice (XML)')],
    ['wc_order_show_completed', t('Order account filter'), t('Only show completed WooCommerce orders on customer account pages')],
    ['wc_customer_login_gdpr', t('Customer GDPR compliance'), t('Provide a GDPR acceptance checkbox on customer login')],
    ['wc_pdf_b2c', t('Enable B2C'), t('Create invoices for business-to-customer relationships')]
]
const schedulerFlags = [
    ['wc_recur_test', t('Test Mode'), t('Replace all recipients with the administrator email address')],
    ['wc_payment_reminder', t('Payment report'), t('Send a daily report of unpaid invoices to the administrator')],
    ['wc_recur', t('Automate invoice submission'), t('Submit recurring invoices to customers on a daily schedule')],
    ['wc_recur_reminder', t('Payment reminder'), t('Send payment reminders when an invoice is due')]
]

const tasks = [
    ['notify', t('Payment notification'), t('Run the payment notifier now and submit outstanding invoice information')],
    ['recur', t('Generate invoices'), t('Generate all recurring invoices for today. Please be careful with this as it may generate (and later submit) duplicates to the recipients')],
    ['submit', t('Submit invoices'), t('Submit all outstanding invoices to their recipients')],
    ['reminder', t('Trigger reminder'), t('Submit all reminders for invoices that are due. Please be careful with this as it will resubmit the reminders and increase the counter')]
]

for (const [name] of [...businessFlags, ...schedulerFlags]) {
    options[name] = Number(options[name]) === 1 ? 1 : 0
}

const placeholders = [
    ['{COMPANY_NAME}', t('Company name')], ['{ADDRESS}', t('Street and number')],
    ['{POSTCODE}', t('Postal code')], ['{CITY}', t('City')],
    ['{EMAIL}', t('Contact email')], ['{VAT_ID}', t('VAT ID')],
    ['{IBAN}', t('IBAN')], ['{BIC}', t('BIC')],
    ['{BANK_NAME}', t('Bank name')], ['{INVOICE_NUMBER}', t('Invoice number')],
    ['{INVOICE_CREATED}', t('Invoice creation date')], ['{DUE_DAYS}', t('Due period in days')],
    ['{DUE_DATE}', t('Due date')]
]

function endpoint(name) {
    return config.endpoints[name] || {}
}

function clearMedia() {
    options.wc_pdf_templatefile = options.wc_pdf_template = ''
    mediaTitle.value = ''
}

async function saveSection(event, section) {
    saving.value = true
    saved.value = false

    try {
        const response = await fetch(event.currentTarget.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: new FormData(event.currentTarget)
        })

        if (!response.ok) {
            throw new Error(`Save failed with status ${response.status}`)
        }

        const result = await response.json()
        if (!result.success || result.data.section !== section) {
            throw new Error('Save failed')
        }

        saved.value = true
    } catch (error) {
        window.alert(t('Settings could not be saved.'))
    } finally {
        saving.value = false
    }
}

function runTask(event, name) {
    const button = event.target.tagName === 'SPAN' ? event.target.parentElement : event.target
    const originalText = button.textContent
    button.disabled = true
    button.textContent = t('Loading...')

    const request = new URLSearchParams({ action: 'InvoiceTask', name })
    return fetch(window.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: request
    })
        .then((response) => response.text())
        .then((response) => {
            const result = Number.parseInt(response, 10)
            const taskTitle = tasks.find(([taskName]) => taskName === name)?.[1] || name
            window.alert(format('Task %1$s returned code %2$s', taskTitle, result))
            return result
        })
        .catch(() => {
            const taskTitle = tasks.find(([taskName]) => taskName === name)?.[1] || name
            window.alert(format('Task %1$s failed', taskTitle))
            return null
        })
        .finally(() => {
            button.disabled = false
            button.textContent = originalText
        })
}

function selectMedia() {
    if (!window.wp?.media) {
        window.alert(t('The WordPress media library is not loaded.'))
        return
    }

    const mediaFrame = wp.media({
        title: t('Select PDF document'),
        library: { type: 'application/pdf' },
        multiple: false,
        button: { text: t('Choose PDF') }
    });

    mediaFrame.off('select')
    mediaFrame.on('select', () => {
        const attachment = mediaFrame.state().get('selection').first().toJSON()
        options.wc_pdf_template = attachment.id
        mediaTitle.value = attachment.title
    })
    mediaFrame.open()
}
</script>

<template>
  <div class="wc-recurring-vue">
    <v-alert
      v-if="config.scheduleActive"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      {{ t('The schedule is properly installed and running') }}
    </v-alert>
    <v-alert
      v-else
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ t('The scheduled task is not installed - Please try to reenable the plugin') }}
    </v-alert>
    <v-alert
      v-if="saved"
      type="success"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="saved = false"
    >
      {{ t('Settings saved') }}
    </v-alert>

    <v-tabs
      v-model="tab"
      color="primary"
      grow
    >
      <v-tab value="general">
        {{ t('General') }}
      </v-tab>
      <v-tab value="invoice">
        {{ t('Invoice template') }}
      </v-tab>
      <v-tab value="email">
        {{ t('Email templates') }}
      </v-tab>
      <v-tab value="export">
        {{ t('Export') }}
      </v-tab>
    </v-tabs>

    <v-window
      v-model="tab"
      class="pt-4"
    >
      <v-window-item value="general">
        <form
          method="post"
          :action="endpoint('general').url"
          @submit.prevent="saveSection($event, 'general')"
        >
          <input
            type="hidden"
            name="_wpnonce"
            :value="endpoint('general').nonce"
          >
          <v-row>
            <v-col
              cols="12"
              md="7"
            >
              <v-card variant="flat">
                <v-card-title>{{ t('Business') }}</v-card-title><v-card-text>
                  <v-text-field
                    v-model="options.wc_company_name"
                    name="wc_company_name"
                    :label="t('Company name')"
                    variant="outlined"
                  />
                  <v-text-field
                    v-model="options.wc_company_email"
                    name="wc_company_email"
                    :label="t('Contact email')"
                    type="email"
                    variant="outlined"
                  />
                  <v-text-field
                    v-model="options.wc_company_vat"
                    name="wc_company_vat"
                    :label="t('VAT ID')"
                    variant="outlined"
                  />
                  <v-text-field
                    v-model="options.wc_invoice_due_days"
                    name="wc_invoice_due_days"
                    :label="t('Due date in days')"
                    type="number"
                    variant="outlined"
                  />
                  <v-select
                    v-model="options.wc_order_subscriptions"
                    name="wc_order_subscriptions"
                    :label="t('Subscription option')"
                    :items="[{ title: t('Customer choose'), value: '' }, ...Object.entries(config.subscriptions).map(([value, title]) => ({ title, value }))]"
                    item-title="title"
                    item-value="value"
                    variant="outlined"
                  />
                  <div class="text-body-2 mb-4">
                    {{ config.companyAddress }} <a :href="config.woocommerceSettingsUrl">{{ t('WooCommerce settings') }}</a>
                  </div>
                  <v-checkbox
                    v-for="field in businessFlags"
                    :key="field[0]"
                    v-model="options[field[0]]"
                    :label="field[1] + ' - ' + field[2]"
                    :name="field[0]"
                    value="1"
                    :true-value="1"
                    :false-value="0"
                    hide-details
                  />
                </v-card-text>
              </v-card>
            </v-col>
            <v-col
              cols="12"
              md="5"
            >
              <v-card
                variant="flat"
                class="mb-4"
              >
                <v-card-title>
                  {{ t('Email details')
                  }}
                </v-card-title><v-card-text>
                  <v-text-field
                    v-model="options.wc_mail_reminder"
                    name="wc_mail_reminder"
                    :label="t('Report recipient')"
                    type="email"
                    variant="outlined"
                  /><v-text-field
                    v-model="options.wc_mail_sender"
                    name="wc_mail_sender"
                    :label="t('Sender address')"
                    variant="outlined"
                  />
                </v-card-text>
              </v-card>
              <v-card variant="flat">
                <v-card-title>
                  {{ t('Task Scheduler')
                  }}
                </v-card-title><v-card-text>
                  <v-checkbox
                    v-for="field in schedulerFlags"
                    :key="field[0]"
                    v-model="options[field[0]]"
                    :label="field[1] + ' - ' + field[2]"
                    :name="field[0]"
                    value="1"
                    :true-value="1"
                    :false-value="0"
                    hide-details
                  />
                  <v-text-field
                    v-model="options.wc_recur_reminder_age"
                    name="wc_recur_reminder_age"
                    :label="t('First reminder (days)')"
                    type="number"
                    variant="outlined"
                  />
                  <v-text-field
                    v-model="options.wc_recur_reminder_interval"
                    name="wc_recur_reminder_interval"
                    :label="t('Reminder interval')"
                    type="number"
                    variant="outlined"
                  />
                  <v-text-field
                    v-model="options.wc_recur_reminder_max"
                    name="wc_recur_reminder_max"
                    :label="t('Max reminders')"
                    type="number"
                    variant="outlined"
                  />
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <div class="d-flex justify-space-between mt-4">
            <v-card
              v-for="task in tasks"
              :key="task[0]"
              variant="flat"
              class="mx-auto ml-0 mr-0"
              min-width="300"
              max-width="300"
              height="160"
              :text="task[2]"
              :title="task[1]"
            >
              <template #append>
                <v-btn
                  variant="outlined"
                  :text="t('Run')"
                  @click="runTask($event, task[0])"
                />
              </template>
            </v-card>
          </div>
          <div class="d-flex justify-end mt-6">
            <v-btn
              color="primary"
              type="submit"
              :loading="saving"
              :disabled="saving"
            >
              {{ t('Save') }}
            </v-btn>
          </div>
        </form>
      </v-window-item>

      <v-window-item value="invoice">
        <form
          method="post"
          :action="endpoint('invoice').url"
          @submit.prevent="saveSection($event, 'invoice')"
        >
          <input
            type="hidden"
            name="_wpnonce"
            :value="endpoint('invoice').nonce"
          ><v-row>
            <v-col
              cols="12"
              md="6"
            >
              <v-card variant="flat">
                <v-card-title>
                  {{ t('Properties')
                  }}
                </v-card-title><v-card-text>
                  <v-text-field
                    v-model="options.wc_pdf_title"
                    name="wc_pdf_title"
                    :label="t('Document Title')"
                    variant="outlined"
                  />
                  <div class="d-flex align-top ga-2 mb-4">
                    <v-text-field
                      :model-value="mediaTitle || options.wc_pdf_templatefile"
                      :label="t('PDF template')"
                      readonly
                      variant="outlined"
                    /><v-btn
                      type="button"
                      variant="tonal"
                      @click="selectMedia"
                    >
                      {{
                        t('Select media') }}
                    </v-btn><v-btn
                      type="button"
                      variant="text"
                      @click="clearMedia"
                    >
                      {{ t('Clear media') }}
                    </v-btn>
                  </div>
                  <input
                    type="hidden"
                    name="wc_pdf_template"
                    :value="options.wc_pdf_template"
                  >
                  <v-textarea
                    v-model="options.wc_pdf_condition"
                    name="wc_pdf_condition"
                    :label="t('Payment terms')"
                    variant="outlined"
                  />
                  <v-textarea
                    v-model="options.wc_pdf_condition_offer"
                    name="wc_pdf_condition_offer"
                    :label="t('Offer terms')"
                    variant="outlined"
                  />
                  <v-textarea
                    v-model="options.wc_pdf_info"
                    name="wc_pdf_info"
                    :label="t('Info block')"
                    variant="outlined"
                  />
                </v-card-text>
              </v-card>
            </v-col><v-col
              cols="12"
              md="6"
            >
              <v-card variant="flat">
                <v-card-title>{{ t('Placeholders') }}</v-card-title><v-table>
                  <thead>
                    <tr>
                      <th>{{ t('Placeholder') }}</th>
                      <th>{{ t('Description') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="item in placeholders"
                      :key="item[0]"
                    >
                      <td>{{ item[0] }}</td>
                      <td>{{ item[1] }}</td>
                    </tr>
                  </tbody>
                </v-table>
              </v-card>
            </v-col>
          </v-row>
          <div class="d-flex justify-end mt-6">
            <v-btn
              color="primary"
              type="submit"
              :loading="saving"
              :disabled="saving"
            >
              {{ t('Save')
              }}
            </v-btn>
          </div>
        </form>
      </v-window-item>

      <v-window-item value="email">
        <form
          method="post"
          :action="endpoint('email').url"
          @submit.prevent="saveSection($event, 'email')"
        >
          <input
            type="hidden"
            name="_wpnonce"
            :value="endpoint('email').nonce"
          >
          <v-card variant="flat">
            <v-card-title>
              {{ t('Email templates')
              }}
            </v-card-title><v-card-text>
              <v-textarea
                v-model="options.wc_payment_message"
                name="wc_payment_message"
                :label="t('Payment report')"
                rows="6"
                variant="outlined"
              /><v-textarea
                v-model="options.wc_recur_message"
                name="wc_recur_message"
                :label="t('Automate invoice submission')"
                rows="8"
                variant="outlined"
              /><v-textarea
                v-model="options.wc_recur_reminder_message"
                name="wc_recur_reminder_message"
                :label="t('Payment reminder')"
                rows="8"
                variant="outlined"
              />
            </v-card-text>
          </v-card>
          <div class="d-flex justify-end mt-6">
            <v-btn
              color="primary"
              type="submit"
              :loading="saving"
              :disabled="saving"
            >
              {{ t('Save')
              }}
            </v-btn>
          </div>
        </form>
      </v-window-item>
      <v-window-item value="export">
        <form
          method="post"
          :action="endpoint('export').url"
          @submit.prevent="saveSection($event, 'export')"
        >
          <input
            type="hidden"
            name="_wpnonce"
            :value="endpoint('export').nonce"
          >
          <v-card variant="flat">
            <v-card-title>{{ t('Export') }}</v-card-title><v-card-text>
              <p>{{ t('The export feature currently supports GnuCash CSV format.') }}</p><v-text-field
                v-model="options.wc_export_locale"
                name="wc_export_locale"
                :label="t('Locale')"
                variant="outlined"
              /><v-textarea
                v-model="options.wc_export_notes"
                name="wc_export_notes"
                :label="t('Notes')"
                variant="outlined"
              /><v-text-field
                v-model="options.wc_export_account"
                name="wc_export_account"
                :label="t('Account name')"
                variant="outlined"
              /><v-text-field
                v-model="options.wc_export_account_posted"
                name="wc_export_account_posted"
                :label="t('Posted account name')"
                variant="outlined"
              /><v-text-field
                v-model="options.wc_export_account_tax"
                name="wc_export_account_tax"
                :label="t('Tax account')"
                variant="outlined"
              />
            </v-card-text>
          </v-card>
          <div class="d-flex justify-end mt-6">
            <v-btn
              color="primary"
              type="submit"
              :loading="saving"
              :disabled="saving"
            >
              {{ t('Save')
              }}
            </v-btn>
          </div>
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

.wc-recurring-vue :deep(.v-field--variant-outlined .v-field-label--floating) {
    background-color: rgb(var(--v-theme-surface));
    padding-inline: 4px;
    z-index: 2;
}
</style>
