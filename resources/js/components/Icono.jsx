import {
    AlertTriangle, BadgeDollarSign, BarChart3, Bell, Briefcase, Building2, CalendarClock, CalendarDays, Circle,
    CreditCard, Factory, FileBarChart, FileText, Film, Flag, Home, KeyRound, LayoutDashboard, LineChart, ListChecks,
    Megaphone, MonitorSmartphone, Network, Package, PlayCircle, Radar, Radio, Receipt, ScrollText, Search, Share2,
    Shield, UserRound, UserSquare, Users, Wallet,
} from 'lucide-react';

/**
 * Íconos disponibles para el menú dinámico (menu_item.icono guarda el nombre).
 * Si se usa un ícono nuevo en la BD, hay que importarlo y agregarlo aquí.
 */
const ICONOS = {
    AlertTriangle, BadgeDollarSign, BarChart3, Bell, Briefcase, Building2, CalendarClock, CalendarDays, CreditCard,
    Factory, FileBarChart, FileText, Film, Flag, Home, KeyRound, LayoutDashboard, LineChart, ListChecks, Megaphone,
    MonitorSmartphone, Network, Package, PlayCircle, Radar, Radio, Receipt, ScrollText, Search, Share2, Shield,
    UserRound, UserSquare, Users, Wallet,
};

export default function Icono({ nombre, size = 18, className = '' }) {
    const Componente = ICONOS[nombre] ?? Circle;
    return <Componente size={size} className={className} aria-hidden />;
}
