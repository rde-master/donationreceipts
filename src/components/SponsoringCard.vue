<template>
	<section v-if="open.length || openReplies" class="dr-sponsoring">
		<div class="dr-sponsoring__head">
			<h3>Sponsoring</h3>
			<RouterLink v-if="openReplies" :to="{ name: 'replies' }" class="dr-warning">
				<NcIconSvgWrapper :path="mdiEmailAlertOutline" :size="18" inline />
				{{ openReplies === 1 ? '1 Antwort-Mail' : `${openReplies} Antwort-Mails` }} zu bearbeiten
			</RouterLink>
		</div>
		<p v-if="!open.length" class="dr-muted">
			Keine offene Aktion.
		</p>
		<ul v-if="open.length" class="dr-sponsoring__legend" aria-hidden="true">
			<li v-for="g in ANSWER_GROUPS" :key="g.key">
				<span class="dr-sponsoring__swatch" :style="{ background: `var(${g.color})` }" />
				{{ g.label }}
			</li>
		</ul>
		<ul v-if="open.length" class="dr-sponsoring__list">
			<li v-for="c in open" :key="c.id" class="dr-sponsoring__item">
				<RouterLink :to="{ name: 'campaign', params: { id: c.id } }" class="dr-sponsoring__name">
					{{ c.name }}
				</RouterLink>
				<div class="dr-sponsoring__figures">
					<span><strong>{{ c.summary.sent }}</strong> angeschrieben</span>
					<span><strong>{{ pledged(c.summary) }}</strong> Zusagen · {{ formatAmount(c.summary.pledgedCents) }}</span>
					<span><strong>{{ c.summary.received }}</strong> Spenden eingegangen</span>
					<span><strong>{{ c.summary.declined }}</strong> Absagen</span>
					<span v-if="c.summary.later"><strong>{{ c.summary.later }}</strong> melden sich noch</span>
					<span><strong>{{ waiting(c.summary) }}</strong> ohne Antwort</span>
				</div>
				<div v-if="c.summary.repliesWithoutStatus || c.summary.incomplete || c.summary.reminderDue" class="dr-sponsoring__todo">
					<span v-if="c.summary.repliesWithoutStatus" class="dr-warning">
						{{ c.summary.repliesWithoutStatus }} Antwort-Mails ohne Status
					</span>
					<span v-if="c.summary.incomplete" class="dr-warning">
						{{ c.summary.incomplete }} Zusagen ohne Angaben
					</span>
					<span v-if="c.summary.reminderDue" class="dr-warning">
						<NcIconSvgWrapper :path="mdiBellAlertOutline" :size="16" inline />
						{{ c.summary.reminderDue }} Erinnerungen fällig
					</span>
				</div>
				<AnswerBar class="dr-sponsoring__bar"
					:counts="answerCountsFromSummary(c.summary)"
					:label="`Antworten zu ${c.name}`"
					compact />
			</li>
		</ul>
	</section>
</template>

<script setup>
import { mdiBellAlertOutline, mdiEmailAlertOutline } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { computed, onMounted, ref } from 'vue'
import { countOpenReplies, listCampaigns } from '../api.js'
import { formatAmount } from '../format.js'
import { answerCountsFromSummary, ANSWER_GROUPS } from '../outcome.js'
import AnswerBar from './charts/AnswerBar.vue'

const campaigns = ref([])
const openReplies = ref(0)
const open = computed(() => campaigns.value.filter((c) => !c.closed))

// pledges with data (answers minus declines and the undecided)
function pledged(s) {
	return s.responded - s.declined - s.later - s.incomplete
}

function waiting(s) {
	return Math.max(0, s.sent - s.responded - s.repliesWithoutStatus)
}

onMounted(async () => {
	try {
		[campaigns.value, openReplies.value] = await Promise.all([listCampaigns(), countOpenReplies()])
	} catch {
		// the dashboard works without it
	}
})
</script>

<style scoped>
.dr-sponsoring {
	padding: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-sponsoring__head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-sponsoring__head h3 {
	margin: 0;
	font-size: 16px;
}

.dr-sponsoring__list {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-top: calc(var(--default-grid-baseline) * 3);
}

.dr-sponsoring__name {
	font-weight: bold;
}

.dr-sponsoring__figures,
.dr-sponsoring__todo {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 4);
	margin-top: var(--default-grid-baseline);
}

.dr-sponsoring__bar {
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.dr-sponsoring__legend {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 3);
	margin-top: calc(var(--default-grid-baseline) * 2);
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.dr-sponsoring__swatch {
	display: inline-block;
	width: 10px;
	height: 10px;
	margin-inline-end: 4px;
	border-radius: 2px;
	vertical-align: middle;
}
</style>
