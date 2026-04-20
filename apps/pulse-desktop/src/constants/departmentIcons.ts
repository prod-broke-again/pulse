import {
  Building2,
  Users,
  ShoppingCart,
  Headphones,
  CreditCard,
  Truck,
  Phone,
  Mail,
  FileText,
  BarChart3,
  MessageCircle,
  Settings,
  Wrench,
  Scale,
  UserPlus,
  Shield,
  Package,
  Briefcase,
  HelpCircle,
  AlertTriangle,
  Heart,
  Globe,
  Tag,
  Zap,
  Clipboard,
} from 'lucide-vue-next'
import type { Component } from 'vue'

/** Must match `App\Support\DepartmentIcons::ALLOWED` on the backend. */
export const DEPARTMENT_ICON_MAP: Record<string, Component> = {
  Building2,
  Users,
  ShoppingCart,
  Headphones,
  CreditCard,
  Truck,
  Phone,
  Mail,
  FileText,
  BarChart3,
  MessageCircle,
  Settings,
  Wrench,
  Scale,
  UserPlus,
  Shield,
  Package,
  Briefcase,
  HelpCircle,
  AlertTriangle,
  Heart,
  Globe,
  Tag,
  Zap,
  Clipboard,
}

export const FALLBACK_DEPARTMENT_ICON: Component = Building2

export function resolveDepartmentIcon(name?: string | null): Component {
  if (!name) {
    return FALLBACK_DEPARTMENT_ICON
  }
  return DEPARTMENT_ICON_MAP[name] ?? FALLBACK_DEPARTMENT_ICON
}
