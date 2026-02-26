<template>
  <div class="row">
    <div class="col-lg-6 mb-3">
      <change-password></change-password>
    </div>
    <div class="col-lg-6 mb-3">
      <user-settings></user-settings>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-6 mb-3">
      <ai-provider-settings></ai-provider-settings>
    </div>
    <div class="col-lg-6 mb-3">
      <google-drive-settings></google-drive-settings>
    </div>
  </div>
</template>

<script>
  import ChangePassword from './ChangePassword.vue';
  import UserSettings from './UserSettings.vue';
  import AiProviderSettings from './AiProviderSettings.vue';
  import GoogleDriveSettings from './GoogleDriveSettings.vue';

  export default {
    name: 'MyProfile',
    components: {
      'change-password': ChangePassword,
      'user-settings': UserSettings,
      'ai-provider-settings': AiProviderSettings,
      'google-drive-settings': GoogleDriveSettings,
    },
  };
</script>
