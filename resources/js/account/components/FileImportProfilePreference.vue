<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapsed collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardFileImportProfilePreference"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('CSV import preference') }}
      </div>
    </div>
    <div
      class="collapse card-body"
      aria-expanded="false"
      id="cardFileImportProfilePreference"
    >
      <div v-if="loading" class="text-muted small">
        {{ __('Loading profiles...') }}
      </div>

      <template v-else>
        <label class="form-label small" for="file-import-profile-preference-select">
          {{ __('Default CSV import profile') }}
        </label>
        <select
          id="file-import-profile-preference-select"
          class="form-select form-select-sm"
          :value="selectedProfileId"
          :disabled="saving"
          @change="onSelectChange($event.target.value)"
        >
          <option :value="null">{{ __('— None —') }}</option>
          <optgroup v-if="systemProfiles.length" :label="__('System profiles')">
            <option
              v-for="profile in systemProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
          <optgroup v-if="userProfiles.length" :label="__('My profiles')">
            <option
              v-for="profile in userProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
        </select>

        <div v-if="successMessage" class="text-success small mt-2">
          <i class="fa fa-check me-1"></i>{{ successMessage }}
        </div>
        <div v-if="errorMessage" class="text-danger small mt-2">
          <i class="fa fa-exclamation-triangle me-1"></i>{{ errorMessage }}
        </div>

        <div class="form-text mt-1">
          {{
            __(
              'This profile will be pre-selected when you import transactions for this account.',
            )
          }}
        </div>
      </template>
    </div>
  </div>
</template>

<script>
  import axios from 'axios';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'FileImportProfilePreference',
    props: {
      accountEntityId: {
        type: Number,
        required: true,
      },
      initialProfileId: {
        type: Number,
        default: null,
      },
    },
    data() {
      return {
        profiles: [],
        loading: false,
        saving: false,
        successMessage: null,
        errorMessage: null,
        selectedProfileId: this.initialProfileId,
      };
    },
    computed: {
      systemProfiles() {
        return this.profiles.filter((p) => p.type === 'system');
      },
      userProfiles() {
        return this.profiles.filter((p) => p.type === 'user');
      },
    },
    mounted() {
      this.fetchProfiles();
    },
    methods: {
      async fetchProfiles() {
        this.loading = true;
        try {
          const response = await axios.get('/api/v1/imports/file-profiles', {
            params: { file_type: 'csv' },
          });
          this.profiles = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; profiles list will stay empty
        } finally {
          this.loading = false;
        }
      },
      async savePreference(profileId) {
        this.saving = true;
        this.successMessage = null;
        this.errorMessage = null;
        try {
          await axios.patch(`/api/v1/accounts/${this.accountEntityId}`, {
            preferred_file_import_profile_id: profileId || null,
          });
          this.selectedProfileId = profileId || null;
          this.successMessage = __('Preference saved.');
          setTimeout(() => {
            this.successMessage = null;
          }, 3000);
        } catch (_error) {
          this.errorMessage = __('Failed to save preference. Please try again.');
        } finally {
          this.saving = false;
        }
      },
      onSelectChange(value) {
        const profileId = value ? Number(value) : null;
        this.savePreference(profileId);
      },
      __,
    },
  };
</script>
