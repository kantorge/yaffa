<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapsed collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardCsvProfilePreference"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('CSV import preference') }}
      </div>
    </div>
    <div
      class="collapse card-body"
      aria-expanded="false"
      id="cardCsvProfilePreference"
    >
      <div v-if="loading" class="text-muted small">
        {{ __('Loading profiles...') }}
      </div>

      <template v-else>
        <label class="form-label small" for="csv-profile-preference-select">
          {{ __('Default CSV import profile') }}
        </label>
        <select
          id="csv-profile-preference-select"
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
  import { computed, onMounted, ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'CsvProfilePreference',
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
    setup(props) {
      const profiles = ref([]);
      const loading = ref(false);
      const saving = ref(false);
      const successMessage = ref(null);
      const errorMessage = ref(null);
      const selectedProfileId = ref(props.initialProfileId);

      const systemProfiles = computed(() =>
        profiles.value.filter((p) => p.type === 'system'),
      );

      const userProfiles = computed(() =>
        profiles.value.filter((p) => p.type === 'user'),
      );

      const fetchProfiles = async () => {
        loading.value = true;

        try {
          const response = await axios.get('/api/v1/imports/csv-profiles');
          profiles.value = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; profiles list will stay empty
        } finally {
          loading.value = false;
        }
      };

      const savePreference = async (profileId) => {
        saving.value = true;
        successMessage.value = null;
        errorMessage.value = null;

        try {
          await axios.patch(`/api/v1/accounts/${props.accountEntityId}`, {
            preferred_csv_import_profile_id: profileId || null,
          });

          selectedProfileId.value = profileId || null;
          successMessage.value = __('Preference saved.');

          setTimeout(() => {
            successMessage.value = null;
          }, 3000);
        } catch (_error) {
          errorMessage.value = __(
            'Failed to save preference. Please try again.',
          );
        } finally {
          saving.value = false;
        }
      };

      const onSelectChange = (value) => {
        const profileId = value ? Number(value) : null;
        savePreference(profileId);
      };

      onMounted(() => {
        fetchProfiles();
      });

      return {
        profiles,
        loading,
        saving,
        successMessage,
        errorMessage,
        selectedProfileId,
        systemProfiles,
        userProfiles,
        onSelectChange,
        __,
      };
    },
  };
</script>
