<script setup lang="ts">
import type { InboxNotification } from '~/composables/useNotifications'

const { items, unreadCount, loading, error, load, markRead, markAllRead, subscribe } = useNotifications()
const { user, refresh } = useAuthUser()

const ICON_FOR_TYPE: Record<string, string> = {
  'case.created': 'briefcase',
  'case.assigned': 'briefcase',
  'case.status-changed': 'check-circle',
  'schedule.changed': 'calendar',
  'device.revoked': 'smartphone',
  'app-release.published': 'download',
}

function iconFor(notification: InboxNotification): string {
  return ICON_FOR_TYPE[notification.type] ?? 'inbox'
}

function timeAgo(value: string): string {
  const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000))
  if (seconds < 60) return 'just now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return new Date(value).toLocaleDateString()
}

function linkFor(notification: InboxNotification): string | undefined {
  return notification.case_id ? `/cases/${notification.case_id}` : undefined
}

async function open(toggle: () => void) {
  toggle()
  if (items.value.length === 0) await load()
}

onMounted(async () => {
  const current = user.value ?? (await refresh())
  if (!current) return

  await load()
  subscribe(current.id)
})
</script>

<template>
  <Popover :width="380" label="Notifications">
    <template #trigger="{ open: isOpen, toggle }">
      <button
        type="button"
        class="relative grid h-10 w-10 place-items-center rounded-sm text-ink-soft transition-colors duration-fast ease-soft hover:bg-surface-sunken hover:text-ink"
        :class="isOpen ? 'bg-surface-sunken text-ink' : ''"
        :aria-label="unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications'"
        @click.stop="open(toggle)"
      >
        <Icon name="bell" class="h-5 w-5" />
        <span
          v-if="unreadCount > 0"
          class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-pill bg-state-danger px-1 text-[10px] font-bold leading-none text-white"
        >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
      </button>
    </template>

    <template #default="{ close }">
      <div class="flex items-center justify-between gap-2 px-2 pb-1.5 pt-1">
        <p class="eyebrow">Notifications</p>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="min-h-9 rounded-sm px-2.5 py-1 text-[12px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
          @click="markAllRead"
        >
          Mark all read
        </button>
      </div>

      <div v-if="loading && items.length === 0" class="space-y-1.5 p-1.5">
        <Skeleton v-for="i in 3" :key="i" class="h-14" rounded="md" />
      </div>

      <InlineAlert v-else-if="error" class="!mb-1">{{ error }}</InlineAlert>

      <EmptyState v-else-if="items.length === 0" icon="bell" message="Nothing to catch up on yet." class="py-6" />

      <ul v-else class="space-y-0.5">
        <li v-for="notification in items" :key="notification.id">
          <component
            :is="linkFor(notification) ? 'NuxtLink' : 'div'"
            :to="linkFor(notification)"
            class="flex items-start gap-2.5 rounded-sm px-2.5 py-2.5 transition-colors duration-fast ease-soft hover:bg-surface-sunken"
            :class="linkFor(notification) ? 'cursor-pointer' : ''"
            @click="markRead(notification.id); linkFor(notification) && close()"
          >
            <span
              class="grid h-8 w-8 flex-none place-items-center rounded-sm"
              :class="notification.read_at ? 'bg-surface-sunken text-ink-faint' : 'bg-primary-soft text-primary-strong'"
            >
              <Icon :name="iconFor(notification)" class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-[13px] leading-snug" :class="notification.read_at ? 'text-ink-soft' : 'font-medium text-ink'">
                {{ notification.message ?? notification.type }}
              </span>
              <span class="mt-0.5 block text-[11.5px] tabular text-ink-faint">{{ timeAgo(notification.created_at) }}</span>
            </span>
            <span v-if="!notification.read_at" class="mt-2 h-1.5 w-1.5 flex-none rounded-full bg-primary"></span>
          </component>
        </li>
      </ul>
    </template>
  </Popover>
</template>
