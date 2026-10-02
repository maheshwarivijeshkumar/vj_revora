import {
    Braces,
    Building2,
    CalendarDays,
    ChartNoAxesCombined,
    CheckSquare,
    Circle,
    ContactRound,
    CreditCard,
    FileInput,
    Gauge,
    GitBranch,
    Handshake,
    Inbox,
    Layers,
    LayoutDashboard,
    LayoutTemplate,
    Megaphone,
    MessageCircle,
    PlugZap,
    RefreshCw,
    ScrollText,
    Settings2,
    Share2,
    ShieldCheck,
    Sparkles,
    Ticket,
    ToggleLeft,
    UsersRound,
    Webhook,
    Workflow,
} from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * Explicit registry of icons referenced by name from navigation config.
 *
 * Deliberately not `import * as icons from 'lucide-vue-next'`: a namespace
 * import defeats tree-shaking and pulls the entire icon set into the bundle
 * (~600kB), which breaks the fast-dashboard-load requirement in §72. Adding a
 * navigation entry means adding its icon here.
 */
const registry: Record<string, Component> = {
    Braces,
    Building2,
    CalendarDays,
    ChartNoAxesCombined,
    CheckSquare,
    ContactRound,
    CreditCard,
    FileInput,
    Gauge,
    GitBranch,
    Handshake,
    Inbox,
    Layers,
    LayoutDashboard,
    LayoutTemplate,
    Megaphone,
    MessageCircle,
    PlugZap,
    RefreshCw,
    ScrollText,
    Settings2,
    Share2,
    ShieldCheck,
    Sparkles,
    Ticket,
    ToggleLeft,
    UsersRound,
    Webhook,
    Workflow,
};

/** Resolves a registered icon, falling back to a neutral glyph. */
export function resolveIcon(name: string): Component {
    return registry[name] ?? Circle;
}
