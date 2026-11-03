<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import EmployeeForm from '@/components/payroll/EmployeeForm.vue';
import { edit, index, show } from '@/routes/employees';
import type {
    Employee,
    IdName,
    Option,
    PayComponentOption,
    Team,
} from '@/types';

defineProps<{
    employee: Employee;
    components: PayComponentOption[];
    employmentTypes: Option[];
    paymentMethods: Option[];
    taxModes: Option[];
    members: IdName[];
    departments: string[];
    designations: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; employee: Employee }) => ({
        breadcrumbs: [
            { title: 'Employees', href: index(props.currentTeam.slug) },
            {
                title: props.employee.name,
                href: show([props.currentTeam.slug, props.employee.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.employee.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${employee.name}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${employee.name}`"
            description="Draft payrolls use the new salary when they are worked out again; approved payslips stay as they are."
        />

        <EmployeeForm
            :employee="employee"
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
