import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * The active tab of a page, kept in the URL (?reiter=…), so links and a
 * reload open the same tab.
 *
 * @param {string[]} ids allowed tab ids, the first is the default
 * @return {import('vue').WritableComputedRef<string>}
 */
export function useTab(ids) {
	const route = useRoute()
	const router = useRouter()
	return computed({
		get: () => (ids.includes(route.query.reiter) ? route.query.reiter : ids[0]),
		set: (id) => router.replace({ query: { ...route.query, reiter: id } }),
	})
}
