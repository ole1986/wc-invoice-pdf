let mediaFrame

export default {
    methods: {
        openMedia(onSelect) {
            if (!window.wp?.media) {
                window.alert('The WordPress media library is not loaded.')
                return
            }

            mediaFrame = wp.media({
                title: 'Select PDF document',
                library: { type: 'application/pdf' },
                multiple: false,
                button: { text: 'Choose PDF' }
            });

            mediaFrame.off('select')
            mediaFrame.on('select', () => {
                onSelect(mediaFrame.state().get('selection').first().toJSON())
            })
            mediaFrame.open()
        },

        runTask(button, name) {
            const originalText = button.textContent
            button.disabled = true
            button.textContent = 'Loading...'

            const request = new URLSearchParams({ action: 'InvoiceTask', name })
            return fetch(window.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: request
            })
                .then((response) => response.text())
                .then((response) => {
                    const result = Number.parseInt(response, 10)
                    window.alert(`Task ${name} returned code ${result}`)
                    return result
                })
                .catch(() => {
                    window.alert(`Task ${name} failed`)
                    return null
                })
                .finally(() => {
                    button.disabled = false
                    button.textContent = originalText
                })
        }
    }
}
