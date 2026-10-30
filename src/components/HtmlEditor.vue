<template>
	<div class="dr-editor">
		<div class="dr-editor__label">
			{{ label }}
		</div>
		<div v-if="editor" class="dr-editor__toolbar" role="toolbar" :aria-label="`Formatierung ${label}`">
			<NcButton v-for="tool in TOOLS"
				:key="tool.title"
				:variant="tool.active?.() ? 'secondary' : 'tertiary'"
				size="small"
				:aria-label="tool.title"
				:title="tool.title"
				:aria-pressed="tool.active ? String(tool.active()) : undefined"
				@click="tool.run">
				<template #icon>
					<NcIconSvgWrapper :path="tool.icon" :size="20" />
				</template>
			</NcButton>
			<NcButton variant="tertiary"
				size="small"
				aria-label="Bild einfügen"
				title="Bild einfügen (PNG, JPG, GIF)"
				:disabled="uploading"
				@click="fileInput.click()">
				<template #icon>
					<NcLoadingIcon v-if="uploading" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiImagePlusOutline" :size="20" />
				</template>
			</NcButton>
			<input ref="fileInput"
				type="file"
				accept="image/png,image/jpeg,image/gif"
				class="hidden-visually"
				@change="insertImage">
			<NcActions v-if="Object.keys(placeholders).length"
				:menu-name="'Platzhalter'"
				:aria-label="'Platzhalter einfügen'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCodeBraces" :size="20" />
				</template>
				<NcActionButton v-for="(text, key) in placeholders"
					:key="key"
					close-after-click
					@click="insertPlaceholder(key)">
					{{ '{' + key + '}' }} – {{ text }}
				</NcActionButton>
			</NcActions>
		</div>
		<EditorContent :editor="editor" class="dr-editor__content" />
	</div>
</template>

<script setup>
import {
	mdiCodeBraces,
	mdiFormatAlignCenter,
	mdiFormatAlignLeft,
	mdiFormatAlignRight,
	mdiFormatBold,
	mdiFormatHeader2,
	mdiFormatHeader3,
	mdiFormatItalic,
	mdiFormatListBulleted,
	mdiFormatListNumbered,
	mdiFormatQuoteClose,
	mdiFormatUnderline,
	mdiImagePlusOutline,
	mdiLink,
	mdiRedo,
	mdiUndo,
} from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { Image } from '@tiptap/extension-image'
import { TextAlign } from '@tiptap/extension-text-align'
import { StarterKit } from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { onBeforeUnmount, ref, watch } from 'vue'
import { errorMessage, uploadMailImage } from '../api.js'

const props = defineProps({
	modelValue: { type: String, default: '' },
	label: { type: String, default: 'Text' },
	// {key: description} offered in the placeholder menu
	placeholders: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

// links may point to a link placeholder; MailHtml replaces it when sending
const LINK_PLACEHOLDER = /^\{(link|ordnerlink)\}$/

const fileInput = ref(null)
const uploading = ref(false)

// Only what the server keeps (MailHtml::sanitize): no code, no rules.
const editor = useEditor({
	content: props.modelValue,
	extensions: [
		StarterKit.configure({
			heading: { levels: [1, 2, 3] },
			code: false,
			codeBlock: false,
			horizontalRule: false,
			link: {
				openOnClick: false,
				protocols: ['mailto'],
				isAllowedUri: (url, ctx) => LINK_PLACEHOLDER.test(url) || ctx.defaultValidate(url),
			},
		}),
		TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right'] }),
		Image,
	],
	onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
})

// e.g. another template chosen in the dialog
watch(() => props.modelValue, (value) => {
	if (editor.value && value !== editor.value.getHTML()) {
		editor.value.commands.setContent(value, { emitUpdate: false })
	}
})

onBeforeUnmount(() => editor.value?.destroy())

const chain = () => editor.value.chain().focus()

const TOOLS = [
	{ title: 'Fett', icon: mdiFormatBold, run: () => chain().toggleBold().run(), active: () => editor.value.isActive('bold') },
	{ title: 'Kursiv', icon: mdiFormatItalic, run: () => chain().toggleItalic().run(), active: () => editor.value.isActive('italic') },
	{ title: 'Unterstrichen', icon: mdiFormatUnderline, run: () => chain().toggleUnderline().run(), active: () => editor.value.isActive('underline') },
	{ title: 'Überschrift', icon: mdiFormatHeader2, run: () => chain().toggleHeading({ level: 2 }).run(), active: () => editor.value.isActive('heading', { level: 2 }) },
	{ title: 'Zwischenüberschrift', icon: mdiFormatHeader3, run: () => chain().toggleHeading({ level: 3 }).run(), active: () => editor.value.isActive('heading', { level: 3 }) },
	{ title: 'Aufzählung', icon: mdiFormatListBulleted, run: () => chain().toggleBulletList().run(), active: () => editor.value.isActive('bulletList') },
	{ title: 'Nummerierte Liste', icon: mdiFormatListNumbered, run: () => chain().toggleOrderedList().run(), active: () => editor.value.isActive('orderedList') },
	{ title: 'Zitat', icon: mdiFormatQuoteClose, run: () => chain().toggleBlockquote().run(), active: () => editor.value.isActive('blockquote') },
	{ title: 'Linksbündig', icon: mdiFormatAlignLeft, run: () => chain().setTextAlign('left').run(), active: () => editor.value.isActive({ textAlign: 'left' }) },
	{ title: 'Zentriert', icon: mdiFormatAlignCenter, run: () => chain().setTextAlign('center').run(), active: () => editor.value.isActive({ textAlign: 'center' }) },
	{ title: 'Rechtsbündig', icon: mdiFormatAlignRight, run: () => chain().setTextAlign('right').run(), active: () => editor.value.isActive({ textAlign: 'right' }) },
	{ title: 'Link', icon: mdiLink, run: editLink, active: () => editor.value.isActive('link') },
	{ title: 'Rückgängig', icon: mdiUndo, run: () => chain().undo().run() },
	{ title: 'Wiederholen', icon: mdiRedo, run: () => chain().redo().run() },
]

function editLink() {
	const current = editor.value.getAttributes('link').href ?? ''
	const url = window.prompt('Adresse des Links (https://…, mailto:… oder {link} / {ordnerlink}); leer entfernt den Link', current)
	if (url === null) {
		return
	}
	const href = url.trim()
	if (href === '') {
		chain().extendMarkRange('link').unsetLink().run()
		return
	}
	if (!LINK_PLACEHOLDER.test(href) && !/^(https?:\/\/|mailto:)/i.test(href)) {
		showError('Bitte eine Adresse mit https://, mailto: oder einen Link-Platzhalter angeben')
		return
	}
	if (editor.value.state.selection.empty && !editor.value.isActive('link')) {
		chain().insertContent({ type: 'text', text: href, marks: [{ type: 'link', attrs: { href } }] }).run()
	} else {
		chain().extendMarkRange('link').setLink({ href }).run()
	}
}

function insertPlaceholder(key) {
	chain().insertContent(`{${key}}`).run()
}

async function insertImage(event) {
	const file = event.target.files?.[0]
	event.target.value = ''
	if (!file) {
		return
	}
	uploading.value = true
	try {
		const src = await uploadMailImage(file)
		chain().setImage({ src, alt: file.name.replace(/\.[^.]+$/, '') }).run()
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		uploading.value = false
	}
}
</script>

<style scoped>
.dr-editor {
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.dr-editor__label {
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 3) 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.dr-editor__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 2px;
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
}

/* Nextcloud styles every div[contenteditable] like a small input (130px, border, margin) */
.dr-editor__content :deep(.ProseMirror[contenteditable]) {
	display: block;
	width: 100%;
	box-sizing: border-box;
	min-height: 200px;
	max-height: 60vh;
	margin: 0;
	padding: calc(var(--default-grid-baseline) * 3);
	overflow-y: auto;
	border: none;
	border-radius: 0;
	background: transparent;
	color: var(--color-main-text);
	font-size: var(--default-font-size);
	line-height: 1.5;
	white-space: pre-wrap;
	cursor: text;
	outline: none;
	box-shadow: none;
}

/* Nextcloud's global styles reset these; show them as in the mail */
.dr-editor__content :deep(p) {
	margin: 0 0 0.8em;
}

.dr-editor__content :deep(h1),
.dr-editor__content :deep(h2),
.dr-editor__content :deep(h3) {
	margin: 0.6em 0 0.4em;
	font-weight: bold;
}

.dr-editor__content :deep(ul) {
	list-style: disc;
	padding-inline-start: 1.5em;
}

.dr-editor__content :deep(ol) {
	list-style: decimal;
	padding-inline-start: 1.5em;
}

.dr-editor__content :deep(blockquote) {
	border-inline-start: 3px solid var(--color-border-dark);
	padding-inline-start: 1em;
	color: var(--color-text-maxcontrast);
}

.dr-editor__content :deep(a) {
	color: var(--color-primary-element);
	text-decoration: underline;
}

.dr-editor__content :deep(img) {
	max-width: 100%;
	height: auto;
}

.dr-editor__content :deep(img.ProseMirror-selectednode) {
	outline: 2px solid var(--color-primary-element);
}
</style>
