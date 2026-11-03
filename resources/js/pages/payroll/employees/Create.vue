<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import EmployeeForm from '@/components/payroll/EmployeeForm.vue';
import type { EmployeePrefill } from '@/components/payroll/EmployeeForm.vue';
import { create, index } from '@/routes/employees';
import type { IdName, Option, PayComponentOption, Team } from '@/types';

defineProps<{
    prefill: EmployeePrefill;
    components: PayComponentOption[];
    employmentTypes: Option[];
    paymentMethods: Option[];
    taxModes: Option[];
    members: IdName[];
    departments: string[];
    designations: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Employees', href: index(props.currentTeam.slug) },
            { title: 'New employee', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New employee" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="New employee"
            description="Add someone to the payroll with their salary, tax and bank details."
        />

        <EmployeeForm
            :prefill="prefill"
            :components="components"
            :employment-types="employmentTypes"
            :payment-methods="paymentMethods"
            :tax-modes="taxModes"
            :members="members"
            :departments="departments"
            :designations="designations"
        />
    </div>
</template>
