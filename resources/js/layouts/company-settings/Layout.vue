<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as customFields } from '@/routes/settings/custom-fields';
import { index as dailyExpenseCategories } from '@/routes/settings/daily-expense-categories';
import { index as dealSources } from '@/routes/settings/deal-sources';
import { index as departments } from '@/routes/settings/departments';
import { index as emailTemplates } from '@/routes/settings/email-templates';
import { index as expenseCategories } from '@/routes/settings/expense-categories';
import { edit as general } from '@/routes/settings/general';
import { edit as invoiceSettings } from '@/routes/settings/invoice-settings';
import { index as lostReasons } from '@/routes/settings/lost-reasons';
import { index as members } from '@/routes/settings/members';
import { edit as numbering } from '@/routes/settings/numbering';
import { index as paymentTerms } from '@/routes/settings/payment-terms';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import { index as pipelines } from '@/routes/settings/pipelines';
import { index as productCategories } from '@/routes/settings/product-categories';
import { index as tags } from '@/routes/settings/tags';
import { index as taxRates } from '@/routes/settings/tax-rates';
import { index as workTypes } from '@/routes/settings/work-types';

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const items = computed(() => {
    const slug = page.props.currentTeam?.slug ?? '';

    return [
        { title: 'General', href: general(slug).url },
        { title: 'Company profile', href: departments(slug).url },
        { title: 'Invoice settings', href: invoiceSettings(slug).url },
        { title: 'Tax rates', href: taxRates(slug).url },
        { title: 'Payment terms', href: paymentTerms(slug).url },
        { title: 'Numbering', href: numbering(slug).url },
        { title: 'Work types', href: workTypes(slug).url },
        { title: 'Product categories', href: productCategories(slug).url },
        { title: 'Pipelines', href: pipelines(slug).url },
        { title: 'Deal sources', href: dealSources(slug).url },
        { title: 'Lost reasons', href: lostReasons(slug).url },
        { title: 'Expense categories', href: expenseCategories(slug).url },
        {
            title: 'Daily expense categories',
            href: dailyExpenseCategories(slug).url,
        },
        { title: 'Payroll', href: payrollSettings(slug).url },
        { title: 'Tags', href: tags(slug).url },
        { title: 'Custom fields', href: customFields(slug).url },
        { title: 'E-mail templates', href: emailTemplates(slug).url },
        { title: 'Members & access', href: members(slug).url },
    ];
});
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Company settings"
            description="How your company appears on documents and how the app works for your team"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-52">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Company settings"
                >
                    <Button
                        v-for="item in items"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">{{ item.title }}</Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1 lg:max-w-4xl">
                <section class="space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
