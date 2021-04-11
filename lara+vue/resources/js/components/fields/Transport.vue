<template>
    <v-container grid-list-lg style="max-width: 1240px" class="py-0 px-0">
        <v-layout>
            <v-flex pb-2 :class="[short ? 'pt-0' : 'pt-5']">
                <h2>Транспортное средство</h2>
            </v-flex>
        </v-layout>

        <v-layout row wrap>
            <v-flex
                v-if="!short"
                xs12 sm12 md4 lg2 py-0
                style="align-self: flex-end"
            >
                <v-checkbox
                    style="margin-top: 0; height: 25px;"
                    label="Без номера"
                    v-model="value.noLicensePlate"
                    @change="value.licensePlate = value.noLicensePlate ? '' : value.licensePlate"
                />
                <v-text-field
                    v-if="!value.noLicensePlate"
                    v-model="value.licensePlate"
                    :rules="[rules.required, rules.number]"
                    label="Гос номер"
                    validate-on-blur
                    required
                    mask='SSSSSSSSS'
                    :placeholder="value.category === 'A' ? '1234AA161' : 'А123AA161'"
                />
                <v-text-field
                    v-if="value.noLicensePlate"
                    label="Гос номер"
                    :disabled="true"
                />
            </v-flex>
            <v-flex
                v-if="!short || showMark"
                xs12 sm6 md4 lg3 py-0
                style="align-self: flex-end"
            >
                <v-autocomplete
                    ref="mark"
                    v-model="value.mark"
                    :items="transportMarks"
                    :rules="[rules.required]"
                    :search-input.sync="markSearch"
                    label="Марка"
                    no-data-text="Марка ТС не найдена"
                    auto-select-first
                    validate-on-blur
                    required
                />
            </v-flex>
            <v-flex
                v-if="!short  || showMark"
                xs12 sm6 md4 lg3 py-0
                style="align-self: flex-end"
            >
                <v-autocomplete
                    ref="model"
                    v-model="value.model"
                    :items="transportModels"
                    :rules="[rules.required]"
                    label="Модель"
                    :search-input.sync="modelSearch"
                    no-data-text="Модель ТС не найдена"
                    :disabled="!this.value.mark"
                    validate-on-blur
                    required
                />
            </v-flex>
            <v-flex
                v-if="!short"
                xs12 sm12 md6 lg4
                py-0
            >
                <v-radio-group
                    row
                    style="margin-top: 0; height: 25px;"
                    v-model="value.idType"
                    @change="nullNumberValues"
                >
                    <v-radio
                         label="VIN"
                         value="vin"
                         class="pb-3"
                    />
                    <v-radio
                         label="№ Кузова"
                         value="bodyNumber"
                         class="pb-3"
                    />
                    <v-radio
                         label="№ Шасси"
                         value="chassisNumber"
                         class="pb-3"
                    />
                </v-radio-group>

                <v-text-field
                    v-if="value.idType==='vin'"
                    v-model="value.vin"
                    :rules="[rules.required, rules.vin]"
                    mask="NNNNNNNNNNNNNNNNN"
                    label="VIN (Идентификационный номер)"
                    @input="value.vin = vinOtoZero(value.vin)"
                    validate-on-blur
                    required
                />
                <v-text-field
                    v-if="value.idType==='bodyNumber'"
                    v-model="value.bodyNumber"
                    :rules="[rules.required, rules.bodyNumber]"
                    mask="NNNNNNNNNNNNNNNNN"
                    label="№ Кузова"
                    validate-on-blur
                    required
                />
                <v-text-field
                    v-if="value.idType==='chassisNumber'"
                    v-model="value.chassisNumber"
                    :rules="[rules.required, rules.chassisNumber]"
                    mask="NNNNNNNNNNNNNNNNN"
                    label="№ Шасси"
                    validate-on-blur
                    required
                />
            </v-flex>

            <v-flex
                xs12 sm6 md3 py-0
                :class="[this.short ? 'lg5' : 'lg3']"
                style="align-self: flex-end"
            >
                <v-text-field
                    v-if="value.category !== 'C'"
                    v-model="value.power"
                    :rules="[rules.required, rules.power]"
                    label="Мощность двигателя (л. с.)"
                    validate-on-blur
                    required
                />
                <v-text-field
                    v-if="value.category === 'C'"
                    v-model="value.maxMass"
                    :rules="[rules.required, rules.maxMass]"
                    label="Максимальная разрешенная масса (кг.)"
                    validate-on-blur
                    required
                />
            </v-flex>
            <v-flex
                v-if="!short" xs12 sm6 md3 lg3 py-0
                style="align-self: flex-end"
            >
                <v-text-field
                    v-model="value.yearIssue"
                    :rules="[rules.required, rules.yearIssue]"
                    mask="####"
                    label="Год выпуска"
                    validate-on-blur
                    required
                />
            </v-flex>
            <v-flex
                v-if="!short && value.category === 'A'" xsflex xs12 sm6 md3 py-0
                :class="[this.short ? 'lg5' : 'lg3']"
                style="align-self: flex-end"
            >
                <v-text-field
                    v-if="value.category === 'A'"
                    v-model="value.frameNumber"
                    :rules="[rules.required]"
                    mask="NNNNNNNNNNNNNNNNN"
                    label="Номер рамы (для категориии А)"
                    validate-on-blur
                    required
                />
            </v-flex>
            <v-flex
                v-if="!short" xs12 sm12 md6 pt-3 pb-0
                :class="[this.short ? 'lg5' : 'lg3']"
                style="align-self: flex-end"
            >
                <span style="font-size: 12px">Категория</span>
                <p style="margin-top: -3px">{{ this.categories[value.category] }}</p>
            </v-flex>
            <v-flex
                v-if="!short && value.category === 'C'" xs12 sm12 md3 pt-0 pb-3
                :class="[this.short ? 'lg5' : 'lg3']"
                style="align-self: flex-end"
            >
                <v-checkbox
                    v-if="value.category === 'C'"
                    style="margin-top: 0;"
                    label="Используется с прицепом"
                    v-model="value.withTrailer"
                />
            </v-flex>
        </v-layout>

        <osago-cardoc
            v-if="!short"
            v-model="value.carDoc"
            :yearIssue="value.yearIssue"
            :dateToday="dateToday"
        />

        <osago-todoc
            v-if="toDocRequired"
            v-model="value.toDoc"
            :showTo="value.yearIssue && value.yearIssue.length === 4 && (((new Date).getFullYear() - value.yearIssue) >= 3)"
            :dateToday="dateToday"
        />

    </v-container>
</template>

<script>
    export default {
        name: 'Transport',
        props: ['value', 'short', 'dateToday'],

        data: function () {
            return {
                markSearch: '',
                modelSearch: '',

                transportMarks: [],
                transportModels: [],

                showMark: false,


                categories: {
                    A: 'Мотоцикл (Категория A)',
                    B: 'Легковое (Категория B)',
                    C: 'Грузовое (Категория C)'
                },

                rules: {
                    required: v => !!v || 'Обязательное поле',
                    power: v => /^\d{1,4}$/.test(v) || 'Неверный формат',
                    maxMass: v => /^\d{4,5}$/.test(v) || 'Неверный формат',
                    vin: v => /^[\dABCDEFGHJKLMNPRSTUVWXYZ]{8,17}$/.test(v) || 'Неверный формат',
                    bodyNumber: v => /^[\dABCDEFGHJKLMNPRSTUVWXYZ]{6,24}$/.test(v) || 'Неверный формат',
                    chassisNumber: v => /^[\dABCDEFGHJKLMNPRSTUVWXYZ]{6,24}$/.test(v) || 'Неверный формат',
                    number: v => /^[АВЕКМНОРСТУХ\d]{4,}$/.test(v) || 'Неверный формат',
                    license: v => /^\d{2}[\dА-ЯA-Z]{2} \d{6}$/.test(v) || 'Неверный формат',
                    yearIssue: v => this.isYearValidRule(v),
                },
            }
        },

        computed: {
            toDocRequired: function() {
                if (this.short || this.isInvalidYear(this.value.yearIssue)) {
                    return false;
                } else {
                    return this.value.yearIssue ? this.dateToday.slice(0, 4) - this.value.yearIssue >= 4 : true;
                }
            }
        },

        created: function() {
            if (this.short && this.value.mark) {
                this.showMark = true;
            }

            axios.get('/dict/marks')
                .then(response => {
                    this.transportMarks = response.data;
                });
        },

        mounted: function() {
            Object.entries(this.$refs).forEach(function (val) {
                val[1].blur = function (e) {
                    setTimeout(() => val[1].blur(e), 0);
                };
            });
        },

        watch: {
            markSearch(mark) {
                if (this.markSearch && this.markSearch !== this.value.mark) {
                    this.value.mark = '';
                    this.value.model = '';
                }

                this.loadModels(mark);
            },

            modelSearch(model) {
                if (this.modelSearch && this.modelSearch !== this.value.model) {
                    this.value.model = '';
                }
                this.loadModel(this.value.mark, model);
            },

            toDocRequired() {
                if (!this.toDocRequired) {
                    this.value.toDoc.endDate = this.value.toDoc.seriesNumber = null;
                }
            }
        },

        methods: {
            loadModels(mark) {
                if(mark) {
                    mark = mark.replace(/\//g, "@@@");
                    return new Promise(resolve => {
                        axios.get('/dict/models/' + mark)
                            .then(response => {
                                this.transportModels = response.data;
                                resolve(response.data)
                            })
                    });
                }
            },

            loadModel(mark, model) {
                if (mark && model) {
                    mark = mark.replace(/\//g, "@@@");
                    model = model.replace(/\//g, "@@@");
                    return new Promise(resolve => {
                        axios.get('/dict/category/' + mark + '/' + model)
                            .then(response => {
                                this.value.category = response.data;
                                resolve(response.data)
                            })
                    });
                }
            },

            vinOtoZero: function (value) {
                if (!value) return '';
                value = value.toString();
                return value.replace(/O/g, '0');
            },

            isYearValidRule: function (year) {
                if (this.isInvalidYear(year)) {
                    return 'Неверно указан год выпуска';
                }
                return false;
            },

            isInvalidYear: function(year) {
                return year < 1930 || year > this.dateToday.slice(0,4);
            },

            nullNumberValues: function() {
                this.value.vin = null;
                this.value.bodyNumber = null;
                this.value.chassisNumber = null;
            }
        },

    }
</script>

<style scoped>

</style>
