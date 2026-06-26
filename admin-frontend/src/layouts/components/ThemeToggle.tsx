import { Moon, Sun, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTheme } from '@/app/theme';
import i18n from '@/i18n';
import { useTranslation } from 'react-i18next';

export function ThemeToggle() {
  const { resolved, toggleMode, lang, setLang } = useTheme();
  const { t } = useTranslation();

  const switchLang = () => {
    const next = lang === 'ar' ? 'en' : 'ar';
    setLang(next);
    void i18n.changeLanguage(next);
  };

  return (
    <div className="flex items-center gap-1">
      <Tooltip>
        <TooltipTrigger asChild>
          <Button variant="ghost" size="icon" onClick={toggleMode} aria-label={t('shell.theme')}>
            {resolved === 'dark' ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t('shell.theme')}</TooltipContent>
      </Tooltip>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button variant="ghost" size="icon" onClick={switchLang} aria-label={t('shell.language')}>
            <Languages className="h-5 w-5" />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t('shell.language')}</TooltipContent>
      </Tooltip>
    </div>
  );
}
