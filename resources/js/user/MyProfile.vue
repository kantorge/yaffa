<template>
  <div class="row">
    <div class="col-md-6">
      <change-password></change-password>
    </div>
    <div class="col-md-6">
      <user-settings></user-settings>
    </div>
  </div>
  <div class="row mt-4">
    <div class="col-12">
      <ai-provider-settings></ai-provider-settings>
    </div>
  </div>
  <div class="row mt-4">
    <div class="col-12">
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
