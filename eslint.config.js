import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
	{ ignores: ['js/**', '3rdparty/**', 'vendor/**', 'node_modules/**'] },
	js.configs.recommended,
	...pluginVue.configs['flat/recommended'],
	{
		files: ['src/**/*.{js,vue}'],
		languageOptions: {
			globals: { ...globals.browser },
		},
		rules: {
			indent: ['error', 'tab'],
			'vue/html-indent': ['error', 'tab'],
			'vue/max-attributes-per-line': 'off',
			'vue/singleline-html-element-content-newline': 'off',
			'vue/html-closing-bracket-newline': 'off',
			'vue/first-attribute-linebreak': 'off',
		},
	},
]
