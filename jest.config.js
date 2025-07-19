module.exports = {
  roots: ['<rootDir>/resources/js'],
  transform: {
    '^.+\\.js$': 'babel-jest',
  },
  testMatch: ['**/__tests__/**/*.test.js'],
};
