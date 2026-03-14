<template>
  <div class="row">
    <div class="col-lg-6 mb-3">
      <change-password></change-password>
    </div>
    <div class="col-lg-6 mb-3">
      <user-settings></user-settings>
    </div>
  </div>
</template>

<script>
  import ChangePassword from './ChangePassword.vue';
  import UserSettings from './UserSettings.vue';

  export default {
    name: 'MyProfile',
    components: {
      'change-password': ChangePassword,
      'user-settings': UserSettings,
    },
  };
</script>
