<template>
    <LoadingComponent :props="loading" />

    <div id="backup" class="db-card db-tab-div active">
        <div class="db-card-header flex items-center justify-between">
            <h3 class="db-card-title">{{ $t("menu.backup") }}</h3>
            <button @click="createBackup" class="db-btn text-white bg-primary">
                <i class="fa-solid fa-plus"></i>
                <span>{{ $t("button.create_backup") }}</span>
            </button>
        </div>
        <div class="db-card-body">
            <!-- Info Alert -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-info text-blue-500 text-xl mt-0.5"></i>
                    <div>
                        <h4 class="font-semibold text-blue-800 mb-1">{{ $t("backup.info_title") }}</h4>
                        <p class="text-blue-700 text-sm">{{ $t("backup.info_description") }}</p>
                    </div>
                </div>
            </div>

            <!-- Backups List -->
            <div v-if="backups.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">{{ $t("label.filename") }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">{{ $t("label.size") }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">{{ $t("label.created_at") }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">{{ $t("label.tables") }}</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">{{ $t("label.actions") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="backup in backups" :key="backup.filename" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-file-zipper text-primary"></i>
                                    <span class="font-medium">{{ backup.filename }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ backup.size }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ formatDate(backup.created_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                    {{ backup.tables_count }} {{ $t("label.tables") }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="downloadBackup(backup.filename)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" :title="$t('button.download')">
                                        <i class="fa-solid fa-download"></i>
                                    </button>
                                    <button @click="confirmRestore(backup)" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" :title="$t('button.restore')">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <button @click="confirmDelete(backup)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" :title="$t('button.delete')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <i class="fa-solid fa-database text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ $t("backup.no_backups") }}</h3>
                <p class="text-gray-500 mb-4">{{ $t("backup.no_backups_description") }}</p>
                <button @click="createBackup" class="db-btn text-white bg-primary">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ $t("button.create_first_backup") }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div v-if="showRestoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation text-yellow-500 text-2xl"></i>
                <h3 class="text-lg font-semibold">{{ $t("backup.restore_confirm_title") }}</h3>
            </div>
            <p class="text-gray-600 mb-2">{{ $t("backup.restore_confirm_message") }}</p>
            <p class="text-sm text-gray-500 mb-4">{{ selectedBackup?.filename }}</p>
            <div class="flex justify-end gap-3">
                <button @click="showRestoreModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                    {{ $t("button.cancel") }}
                </button>
                <button @click="restoreBackup" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    {{ $t("button.restore") }}
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 mb-4">
                <i class="fa-solid fa-trash text-red-500 text-2xl"></i>
                <h3 class="text-lg font-semibold">{{ $t("backup.delete_confirm_title") }}</h3>
            </div>
            <p class="text-gray-600 mb-4">{{ $t("backup.delete_confirm_message") }}</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                    {{ $t("button.cancel") }}
                </button>
                <button @click="deleteBackup" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    {{ $t("button.delete") }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent.vue";
import alertService from "../../../../services/alertService";
import axios from "axios";

export default {
    name: "BackupComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: { isActive: false },
            backups: [],
            showRestoreModal: false,
            showDeleteModal: false,
            selectedBackup: null,
        };
    },
    mounted() {
        this.fetchBackups();
    },
    methods: {
        async fetchBackups() {
            try {
                this.loading.isActive = true;
                const response = await axios.get('/admin/setting/system-backup');
                if (response.data.status) {
                    this.backups = response.data.data;
                }
            } catch (err) {
                alertService.error(err.response?.data?.message || err.message);
            } finally {
                this.loading.isActive = false;
            }
        },

        async createBackup() {
            try {
                this.loading.isActive = true;
                const response = await axios.post('/admin/setting/system-backup');
                if (response.data.status) {
                    alertService.success(this.$t("backup.created_success"));
                    this.fetchBackups();
                } else {
                    alertService.error(response.data.message);
                }
            } catch (err) {
                alertService.error(err.response?.data?.message || err.message);
            } finally {
                this.loading.isActive = false;
            }
        },

        confirmRestore(backup) {
            this.selectedBackup = backup;
            this.showRestoreModal = true;
        },

        async restoreBackup() {
            try {
                this.showRestoreModal = false;
                this.loading.isActive = true;
                const response = await axios.post('/admin/setting/system-backup/restore', {
                    filename: this.selectedBackup.filename
                });
                if (response.data.status) {
                    alertService.success(this.$t("backup.restored_success"));
                } else {
                    alertService.error(response.data.message);
                }
            } catch (err) {
                alertService.error(err.response?.data?.message || err.message);
            } finally {
                this.loading.isActive = false;
            }
        },

        confirmDelete(backup) {
            this.selectedBackup = backup;
            this.showDeleteModal = true;
        },

        async deleteBackup() {
            try {
                this.showDeleteModal = false;
                this.loading.isActive = true;
                const response = await axios.delete(`/admin/setting/system-backup/${this.selectedBackup.filename}`);
                if (response.data.status) {
                    alertService.success(this.$t("backup.deleted_success"));
                    this.fetchBackups();
                } else {
                    alertService.error(response.data.message);
                }
            } catch (err) {
                alertService.error(err.response?.data?.message || err.message);
            } finally {
                this.loading.isActive = false;
            }
        },

        downloadBackup(filename) {
            window.open(`/admin/setting/system-backup/download/${filename}`, '_blank');
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('ar-EG', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
};
</script>
