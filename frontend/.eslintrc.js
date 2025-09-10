module.exports = {
  root: true,
  extends: ['@nuxtjs/eslint-config-typescript'],
  rules: {
    // Relax some rules for development
    '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
    'vue/multi-word-component-names': 'off',
    'vue/no-multiple-template-root': 'off'
  }
}