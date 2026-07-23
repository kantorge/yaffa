<template>
  <div class="row">
    <div class="col-lg-6 mb-3">
      <change-password></change-password>
    </div>
    <div class="col-lg-6 mb-3">
      <user-settings></user-settings>
    </div>
    <div class="col-lg-6 mb-3">
      <two-factor-settings></two-factor-settings>
    </div>
    <div class="col-lg-6 mb-3">
      <api-token-manager></api-token-manager>
    </div>
  </div>
</template>

<script>
  import ChangePassword from './ChangePassword.vue';
  import UserSettings from './UserSettings.vue';
  import ApiTokenManager from './ApiTokenManager.vue';
  import TwoFactorSettings from './TwoFactorSettings.vue';

  export default {
    name: 'MyProfile',
    components: {
      'change-password': ChangePassword,
      'user-settings': UserSettings,
      'api-token-manager': ApiTokenManager,
      'two-factor-settings': TwoFactorSettings,
    },
  };
</script>
