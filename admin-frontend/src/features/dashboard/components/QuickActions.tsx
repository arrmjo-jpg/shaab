import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Clapperboard,
  FileText,
  Film,
  MessageSquare,
  Newspaper,
  Settings,
  Users,
  Zap,
  type LucideIcon,
} from 'lucide-react';
import { Panel } from '@/components/analytics/AnalyticsKit';
import { useAuth } from '@/stores/auth.store';
import { paths } from '@/router/paths';

interface QuickAction {
  key: string;
  to: string;
  icon: LucideIcon;
  permission: string;
}

// روابط لعمليات قائمة فقط — كلٌّ محكوم بصلاحيته الحالية (لا صلاحيات جديدة).
const ACTIONS: QuickAction[] = [
  { key: 'addNews', to: paths.articlesCreate, icon: Newspaper, permission: 'articles.create' },
  { key: 'addArticle', to: paths.articlesCreate, icon: FileText, permission: 'articles.create' },
  { key: 'addReel', to: paths.reelsCreate, icon: Clapperboard, permission: 'reels.create' },
  { key: 'addVideo', to: paths.vlVideosCreate, icon: Film, permission: 'videos.create' },
  { key: 'manageUsers', to: paths.users, icon: Users, permission: 'users.view' },
  { key: 'manageComments', to: paths.comments, icon: MessageSquare, permission: 'comments.view' },
  { key: 'siteSettings', to: paths.settings, icon: Settings, permission: 'settings.view' },
];

/** إجراءات سريعة — روابط فقط (بلا fetch)، تُخفى البطاقة بالكامل إن لم تتوفّر أي صلاحية. */
export default function QuickActions() {
  const { t } = useTranslation('common');
  const { hasPermission } = useAuth();
  const allowed = ACTIONS.filter((a) => hasPermission(a.permission));
  if (allowed.length === 0) return null;

  return (
    <Panel title={t('dashboard.quickActions.title')} icon={Zap}>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        {allowed.map(({ key, to, icon: Icon }) => (
          <Link
            key={key}
            to={to}
            className="flex items-center gap-2 border border-border bg-background p-3 text-sm font-medium transition-colors hover:bg-muted"
          >
            <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
            <span className="truncate">{t(`dashboard.quickActions.${key}`)}</span>
          </Link>
        ))}
      </div>
    </Panel>
  );
}
