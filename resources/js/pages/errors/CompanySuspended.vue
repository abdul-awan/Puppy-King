<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Ban } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as teamsIndex } from '@/routes/teams';

/**
 * Shown instead of the workspace of a company the platform administrators suspended.
 */
defineProps<{
    company: string;
    reason: string | null;
}>();
</script>

<template>
    <Head :title="`${company} is suspended`" />

    <div class="flex flex-1 items-start justify-center p-4 md:p-10">
        <Card class="w-full max-w-lg">
            <CardContent class="space-y-4 pt-6 text-center">
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-300"
                >
                    <Ban class="size-6" />
                </div>
                <h1 class="text-lg font-semibold">
                    {{ company }} is suspended
                </h1>
                <p
                    v-if="reason"
                    class="rounded-md bg-muted p-3 text-sm whitespace-pre-line"
                >
                    {{ reason }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Nobody can work in this company at the moment. Its data is
                    kept safe. Please contact the support of the platform to
                    reactivate it.
                </p>
                <p class="text-sm text-muted-foreground">
                    Do you belong to another company? Choose it in the menu at
                    the top left, or see all your companies.
                </p>
                <Button variant="outline" as-child>
                    <Link :href="teamsIndex().url">Your companies</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
