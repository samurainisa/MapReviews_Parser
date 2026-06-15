import js from '@eslint/js'
import ts from 'typescript-eslint'
import vue from 'eslint-plugin-vue'
import globals from 'globals'

export default ts.config(
  { ignores: ['dist', 'node_modules'] },
  js.configs.recommended,
  ...ts.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['**/*.{ts,vue}'],
    languageOptions: {
      globals: { ...globals.browser },
      parserOptions: {
        // Парсер TS для <script lang="ts"> внутри .vue
        parser: ts.parser,
      },
    },
    rules: {
      // Строгие сравнения — запрет == / != (кроме == null).
      eqeqeq: ['error', 'always', { null: 'ignore' }],
      'vue/multi-word-component-names': 'off',
    },
  },
)
