import { useForm, Controller, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { SelectField } from '@/components/form/SelectField';
import { TextareaField } from '@/components/form/TextareaField';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty } from '../hooks';
import { aiSchema, type AiValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['openai_api_key', 'gemini_api_key'];
const PROMPTS: (keyof AiValues)[] = [
  'ai_news_prompt', 'ai_article_prompt', 'ai_default_prompt',
  'ai_rewrite_prompt', 'ai_seo_prompt', 'ai_tags_prompt',
];

export default function AiProvidersPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();

  const ai = q.data?.ai;
  const values: AiValues = ai
    ? {
        ai_enabled: ai.ai_enabled,
        ai_provider: ai.provider,
        openai_api_key: '',
        openai_base_url: ai.openai.base_url,
        openai_model: ai.openai.model,
        openai_temperature: ai.openai.temperature,
        openai_max_tokens: ai.openai.max_tokens,
        openai_timeout: ai.openai.timeout,
        openai_writing_style: ai.openai.writing_style,
        gemini_api_key: '',
        gemini_model: ai.gemini.model,
        gemini_temperature: ai.gemini.temperature,
        gemini_max_tokens: ai.gemini.max_tokens,
        gemini_timeout: ai.gemini.timeout,
        ai_news_prompt: ai.prompts.news_prompt,
        ai_article_prompt: ai.prompts.article_prompt,
        ai_default_prompt: ai.prompts.default_prompt,
        ai_rewrite_prompt: ai.prompts.rewrite_prompt,
        ai_seo_prompt: ai.prompts.seo_prompt,
        ai_tags_prompt: ai.prompts.tags_prompt,
      }
    : ({} as AiValues);

  const { register, handleSubmit, control } = useForm<AiValues>({
    resolver: zodResolver(aiSchema),
    values,
  });
  const provider = useWatch({ control, name: 'ai_provider' });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));
  const activeBadge = (p: string) =>
    provider === p ? <Badge variant="success">{t('ai.active')}</Badge> : null;

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('ai.title')} description={t('ai.desc')}>
        <Controller control={control} name="ai_enabled" render={({ field }) => (
          <SwitchField label={t('ai.ai_enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <Controller control={control} name="ai_provider" render={({ field }) => (
          <SelectField
            label={t('ai.provider')}
            value={field.value}
            onChange={field.onChange}
            options={[{ value: 'openai', label: 'OpenAI' }, { value: 'gemini', label: 'Gemini' }]}
          />
        )} />
      </SettingsSection>

      <div className={cn('transition-opacity', provider !== 'openai' && 'opacity-60')}>
        <SettingsSection title={t('ai.openaiCard')}>
          <div className="-mt-2 mb-2 flex">{activeBadge('openai')}</div>
          <SecretField label={t('ai.api_key')} configured={q.data.ai.openai.api_key_configured} {...register('openai_api_key')} />
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField label={t('ai.base_url')} {...register('openai_base_url')} />
            <TextField label={t('ai.model')} {...register('openai_model')} />
            <TextField label={t('ai.temperature')} type="number" step="0.1" {...register('openai_temperature', { valueAsNumber: true })} />
            <TextField label={t('ai.max_tokens')} type="number" {...register('openai_max_tokens', { valueAsNumber: true })} />
            <TextField label={t('ai.timeout')} type="number" {...register('openai_timeout', { valueAsNumber: true })} />
          </div>
          <TextareaField label={t('ai.writing_style')} {...register('openai_writing_style')} />
        </SettingsSection>
      </div>

      <div className={cn('transition-opacity', provider !== 'gemini' && 'opacity-60')}>
        <SettingsSection title={t('ai.geminiCard')}>
          <div className="-mt-2 mb-2 flex">{activeBadge('gemini')}</div>
          <SecretField label={t('ai.api_key')} configured={q.data.ai.gemini.api_key_configured} {...register('gemini_api_key')} />
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField label={t('ai.model')} {...register('gemini_model')} />
            <TextField label={t('ai.temperature')} type="number" step="0.1" {...register('gemini_temperature', { valueAsNumber: true })} />
            <TextField label={t('ai.max_tokens')} type="number" {...register('gemini_max_tokens', { valueAsNumber: true })} />
            <TextField label={t('ai.timeout')} type="number" {...register('gemini_timeout', { valueAsNumber: true })} />
          </div>
        </SettingsSection>
      </div>

      <SettingsSection title={t('ai.promptsCard')}>
        {PROMPTS.map((k) => (
          <TextareaField key={k} label={t(`ai.${k.replace('ai_', '')}`)} {...register(k)} />
        ))}
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
