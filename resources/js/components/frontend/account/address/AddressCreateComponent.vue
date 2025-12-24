<template>
    <LoadingComponent :props="loading" />
    <button data-modal="#address"  @click="add" type="button"
        class="w-full rounded-2xl py-10 flex items-center justify-center gap-2.5 text-primary bg-[#E7FFF3]">
        <i class="lab-fill-circle-plus text-lg"></i>
        <span class="text-lg font-semibold capitalize">{{ addButton.title }}</span>
    </button>

    <div id="address" class="modal address ff-modal">
        <div class="modal-dialog">
            <div class="modal-header border-none pb-0">
                <h3 class="capitalize font-medium">{{ $t('label.your_address') }}</h3>
                <button class="modal-close fa-solid fa-xmark text-xl text-slate-400 hover:text-red-500"
                    @click="reset"></button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="save">
                    <!-- Governorate Selection -->
                    <div class="mb-3">
                        <label class="text-xs leading-6 capitalize mb-1 text-heading">{{ $t('label.governorate') }} *</label>
                        <select v-model="props.form.governorate" 
                            v-bind:class="errors.governorate ? 'invalid' : ''"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                            <option value="">{{ $t('label.select_governorate') }}</option>
                            <option v-for="gov in governorates" :key="gov" :value="gov">{{ gov }}</option>
                        </select>
                        <small class="db-field-alert" v-if="errors.governorate">{{ errors.governorate[0] }}</small>
                    </div>

                    <!-- City -->
                    <div class="mb-3">
                        <label class="text-xs leading-6 capitalize mb-1 text-heading">{{ $t('label.city') }}</label>
                        <input type="text" v-model="props.form.city"
                            v-bind:class="errors.city ? 'invalid' : ''"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                        <small class="db-field-alert" v-if="errors.city">{{ errors.city[0] }}</small>
                    </div>

                    <!-- Street -->
                    <div class="mb-3">
                        <label class="text-xs leading-6 capitalize mb-1 text-heading">{{ $t('label.street') }}</label>
                        <input type="text" v-model="props.form.street"
                            v-bind:class="errors.street ? 'invalid' : ''"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                        <small class="db-field-alert" v-if="errors.street">{{ errors.street[0] }}</small>
                    </div>

                    <!-- Building Number -->
                    <div class="mb-3">
                        <label class="text-xs leading-6 capitalize mb-1 text-heading">{{ $t('label.building_number') }}</label>
                        <input type="text" v-model="props.form.building_number"
                            v-bind:class="errors.building_number ? 'invalid' : ''"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                        <small class="db-field-alert" v-if="errors.building_number">{{ errors.building_number[0] }}</small>
                    </div>

                    <!-- Apartment -->
                    <div class="mb-3">
                        <label for="apartment" class="text-xs leading-6 capitalize mb-1 text-heading">{{
                            $t('label.apartment_and_flat')
                        }}</label>
                        <input type="text" id="apartment" v-model="props.form.apartment"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label class="text-xs leading-6 capitalize mb-1 text-heading">{{ $t('label.phone') }}</label>
                        <input type="text" v-model="props.form.phone"
                            v-bind:class="errors.phone ? 'invalid' : ''"
                            class="h-12 w-full rounded-lg border py-1.5 px-2 border-[#D9DBE9]">
                        <small class="db-field-alert" v-if="errors.phone">{{ errors.phone[0] }}</small>
                    </div>

                    <!-- Label Selection -->
                    <div class="mb-6">
                        <h3 class="capitalize font-medium mb-2">{{ $t('label.add_label') }}</h3>
                        <nav class="flex flex-wrap gap-3 active-group">
                            <button @click="changeSwitchLabel(labelEnum.HOME)"
                                :class="props.switchLabel === labelEnum.HOME ? 'active' : ''"
                                v-on:click="this.props.status = false; this.props.form.label = $t('label.home')"
                                :value="labelEnum.HOME" type="button"
                                class="flex items-center gap-2 rounded-lg p-4 border bg-[#F7F7FC] border-[#F7F7FC]">
                                <i class="lab lab-fill-home text-base leading-none"></i>
                                <span class="text-sm capitalize font-medium leading-none text-heading">{{
                                    $t('label.home')
                                }}</span>
                            </button>
                            <button @click="changeSwitchLabel(labelEnum.WORK)"
                                :class="props.switchLabel === labelEnum.WORK ? 'active' : ''"
                                v-on:click="this.props.status = false; this.props.form.label = $t('label.work')"
                                :value="labelEnum.WORK" type="button"
                                class="flex items-center gap-2 rounded-lg p-4 border bg-[#F7F7FC] border-[#F7F7FC]">
                                <i class="lab lab-fill-briefcase text-base leading-none"></i>
                                <span class="text-sm capitalize font-medium leading-none text-heading">
                                    {{ $t('label.work') }}
                                </span>
                            </button>
                            <button @click="changeSwitchLabel(labelEnum.OTHER)"
                                :class="props.switchLabel === labelEnum.OTHER ? 'active' : ''"
                                v-on:click="this.props.status = true; this.props.form.label = ''; this.errors.label = ''"
                                :value="labelEnum.OTHER" type="button"
                                class="flex items-center gap-2 rounded-lg p-4 border bg-[#F7F7FC] border-[#F7F7FC]">
                                <i class="lab lab-more-square text-base leading-none"></i>
                                <span class="text-sm capitalize font-medium leading-none text-heading">{{
                                    $t('label.other')
                                }}</span>
                            </button>
                        </nav>
                        <small class="db-field-alert" v-if="errors.label && props.switchLabel !== labelEnum.OTHER">{{
                            errors.label[0]
                        }}</small>
                        <div v-if="props.status" :class="!props.status ? 'h-0' : ''" class="overflow-hidden transition">
                            <input type="text" :placeholder="$t('label.type_label_name')" v-model="props.form.label"
                                v-bind:class="errors.label ? 'invalid' : ''"
                                class="h-10 w-full rounded-lg border mt-5 py-1.5 px-4 placeholder:text-xs border-[#D9DBE9]">
                            <small class="db-field-alert" v-if="errors.label">{{ errors.label[0] }}</small>
                        </div>
                    </div>
                    <button type="submit" class="rounded-3xl text-base py-3 px-3 font-medium w-full text-white bg-primary">
                        {{ $t('button.confirm_location') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import AddressCreateModalComponent from "../../components/buttons/AddressCreateModalComponent.vue";
import labelEnum from "../../../../enums/modules/labelEnum";
import appService from "../../../../services/appService";
import alertService from "../../../../services/alertService";
import LoadingComponent from "../../components/LoadingComponent.vue";

export default {
    name: "AddressComponent",
    components: { AddressCreateModalComponent, LoadingComponent },
    props: {
        props: Object,
        getLocation: Function
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            labelEnum: labelEnum,
            switchLabel: "",
            errors: {},
            governorates: [
                'القاهرة', 'الجيزة', 'الإسكندرية', 'القليوبية',
                'الشرقية', 'الدقهلية', 'البحيرة', 'كفر الشيخ',
                'الغربية', 'المنوفية', 'دمياط', 'بورسعيد',
                'الإسماعيلية', 'السويس', 'شمال سيناء', 'جنوب سيناء',
                'المنيا', 'بني سويف', 'الفيوم', 'أسيوط',
                'سوهاج', 'قنا', 'الأقصر', 'أسوان',
                'البحر الأحمر', 'الوادي الجديد', 'مطروح'
            ]
        }
    },
    computed: {
        addButton: function () {
            return {title: this.$t("button.add_new_address")}
        }
    },
    methods: {
        add: function () {
            appService.modalShow("#address");
        },
        changeSwitchLabel: function (id) {
            this.props.switchLabel = id;
        },
        reset: function () {
            appService.modalHide();
            this.$store.dispatch("frontendAddress/reset").then().catch();
            this.errors = {};
            this.$props.props.form = {
                governorate: "",
                city: "",
                street: "",
                building_number: "",
                apartment: "",
                phone: "",
                label: "",
            };
            this.$props.props.status = false;
            this.$props.props.switchLabel = "";
        },
        save: function () {
            try {
                const tempId = this.$store.getters["frontendAddress/temp"].temp_id;
                this.loading.isActive = true;
                this.$store.dispatch("frontendAddress/save", this.props).then((res) => {
                    this.getLocation(res.data.data);
                    appService.modalHide('#address');
                    this.loading.isActive = false;
                    alertService.successFlip(tempId === null ? 0 : 1, this.$t("label.address"));
                    this.props.form = {
                        governorate: "",
                        city: "",
                        street: "",
                        building_number: "",
                        apartment: "",
                        phone: "",
                        label: "",
                    };
                    this.props.status = false;
                    this.props.switchLabel = "";
                    this.errors = {};
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.errors;
                });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
    }
}
</script>

