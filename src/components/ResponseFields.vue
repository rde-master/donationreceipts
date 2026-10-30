<template>
	<fieldset class="dr-response-fields" :disabled="disabled">
		<section>
			<h3>Angaben für die Zuwendungsbestätigung</h3>
			<div class="dr-row dr-radios">
				<NcCheckboxRadioSwitch v-model="form.kind"
					type="radio"
					value="company"
					:name="`${id}-kind`">
					Firma
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="form.kind"
					type="radio"
					value="person"
					:name="`${id}-kind`">
					Privatperson
				</NcCheckboxRadioSwitch>
			</div>
			<NcTextField v-if="form.kind === 'company'"
				v-model="form.company"
				label="Firmenname"
				required />
			<div v-else class="dr-row">
				<NcTextField v-model="form.firstName" label="Vorname" required />
				<NcTextField v-model="form.lastName" label="Nachname" required />
			</div>
			<NcTextField v-model="form.street" label="Straße und Hausnummer" required />
			<div class="dr-row">
				<NcTextField v-model="form.zip" label="PLZ" class="dr-zip" required />
				<NcTextField v-model="form.city" label="Ort" required />
			</div>
			<NcTextField v-model="form.country" label="Land (leer = Deutschland)" />
		</section>

		<section>
			<h3>Spende</h3>
			<div class="dr-row dr-radios">
				<NcCheckboxRadioSwitch v-model="form.type"
					type="radio"
					value="money"
					:name="`${id}-type`">
					Geldspende
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="form.type"
					type="radio"
					value="goods"
					:name="`${id}-type`">
					Sachspende
				</NcCheckboxRadioSwitch>
			</div>
			<NcTextField v-model="form.amountText"
				:label="form.type === 'goods' ? 'Wert der Sachspende in Euro' : 'Betrag in Euro'"
				placeholder="z. B. 250,00"
				:error="amountInvalid"
				:helper-text="amountInvalid ? 'Bitte einen gültigen Betrag eingeben' : ''"
				required />
			<template v-if="form.type === 'goods'">
				<NcTextArea v-model="form.goodsDescription"
					label="Was wird gespendet? (genaue Bezeichnung, ggf. Alter und Zustand)"
					resize="vertical"
					rows="3"
					required />
				<fieldset>
					<legend>Die Sachspende stammt aus dem</legend>
					<NcCheckboxRadioSwitch v-for="(label, value) in ORIGINS"
						:key="value"
						v-model="form.goodsOrigin"
						type="radio"
						:value="value"
						:name="`${id}-origin`">
						{{ label }}
					</NcCheckboxRadioSwitch>
				</fieldset>
			</template>
		</section>

		<section>
			<h3>Sind Freikarten erwünscht?</h3>
			<NcCheckboxRadioSwitch v-for="(label, value) in tickets"
				:key="value"
				v-model="form.tickets"
				type="radio"
				:value="value"
				:name="`${id}-tickets`">
				{{ label }}
			</NcCheckboxRadioSwitch>
		</section>

		<section>
			<h3>Darf die Unterstützung öffentlich bekannt gegeben werden (Logo auf Plakaten)?</h3>
			<div class="dr-row dr-radios">
				<NcCheckboxRadioSwitch v-model="form.publish"
					type="radio"
					value="yes"
					:name="`${id}-publish`">
					Ja
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="form.publish"
					type="radio"
					value="no"
					:name="`${id}-publish`">
					Nein
				</NcCheckboxRadioSwitch>
			</div>
			<!-- e.g. the logo upload of the public form -->
			<slot name="publish" />
		</section>

		<section>
			<h3>Hinweis</h3>
			<NcTextArea v-model="form.remarks"
				label="Weitere Hinweise (optional)"
				resize="vertical"
				rows="3" />
		</section>
	</fieldset>
</template>

<script setup>
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, useId } from 'vue'
import { ORIGINS, parseAmount } from '../format.js'

// form state from responseForm() (src/responseForm.js)
const form = defineModel({ type: Object, required: true })

defineProps({
	// {value: label} of the ticket choices
	tickets: { type: Object, required: true },
	disabled: { type: Boolean, default: false },
})

// radio groups need page-wide unique names (dialog and list can both be open)
const id = useId()

const amountInvalid = computed(() => form.value.amountText !== '' && parseAmount(form.value.amountText) === null)
</script>

<style scoped>
.dr-response-fields {
	border: none;
	padding: 0;
	margin: 0;
}

.dr-response-fields section {
	margin-bottom: calc(var(--default-grid-baseline) * 6);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-radios > * {
	flex: 0 0 auto;
}
</style>
