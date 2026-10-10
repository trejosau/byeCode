import { Link, usePage } from '@inertiajs/react';
import {
    LayoutGrid,
    ClipboardList,
    Users,
    Shirt,
    FlaskConical,
    BrainCircuit,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Resumen',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Solicitudes', href: '/requests', icon: ClipboardList },
    { title: 'Cotizaciones', href: '/cases', icon: ClipboardList },
    { title: 'Clientes', href: '/customers', icon: Users },
    { title: 'Catálogo de blanks', href: '/products', icon: Shirt },
    {
        title: 'Simulador e importaciones',
        href: '/imports',
        icon: FlaskConical,
    },
    {
        title: 'Inteligencia y consumo',
        href: '/intelligence',
        icon: BrainCircuit,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;
    const navigation =
        auth.user.role === 'admin'
            ? [{ title: 'Administración', href: '/admin', icon: LayoutGrid }]
            : auth.user.role === 'customer'
              ? [
                    {
                        title: 'Mis solicitudes',
                        href: '/my/requests',
                        icon: ClipboardList,
                    },
                    { title: 'Blanks', href: '/catalog', icon: Shirt },
                ]
              : mainNavItems;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navigation} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
