import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, ChevronDown, ChevronUp, Plus, Power, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { LoadingState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { usePoll, useCreatePoll, useUpdatePoll, useToggleActivePoll } from '../hooks';
import {
  POLL_AUDIENCE_MODES,
  POLL_RESULT_VISIBILITIES,
  type PollAudienceMode,
  type PollResultVisibility,
  type PollState,
  type PollUpsertPayload,
} from '@/types/polls.types';

/** صفّ خيار في المحرّر — id موجود للخيارات المُخزَّنة، votes_count لتعطيل الحذف. */
interface OptionRow {
  id?: number;
  label: string;
  votes_count: number;
}

interface PollForm {
  question: string;
  allow_multiple: boolean;
  starts_at: string;
  ends_at: string;
  audience_mode: PollAudienceMode;
  result_visibility: PollResultVisibility;
  options: OptionRow[];
}

const EMPTY: PollForm = {
  question: '',
  allow_multiple: false,
  starts_at: '',
  ends_at: '',
  audience_mode: 'public',
  result_visibility: 'always',
  options: [
    { label: '', votes_count: 0 },
    { label: '', votes_count: 0 },
  ],
};

const STATE_TONE: Record<PollState, 'default' | 'success' | 'muted'> = {
  inactive: 'muted',
  scheduled: 'default',
  open: 'success',
  closed: 'muted',
};

const MIN_OPTIONS = 2;

/** ISO → قيمة datetime-local محلية (YYYY-MM-DDTHH:mm). */
function toLocalInput(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function PollFormPage() {
  const { t } = useTranslation('polls');
  const navigate = useNavigate();
  const params = useParams();
  const pollId = params.id ? Number(params.id) : null;
  const isEdit = pollId !== null;
  const { hasPermission } = useAuth();
  const canPublish = hasPermission('polls.publish');

  const detail = usePoll(pollId);
  const create = useCreatePoll();
  const update = useUpdatePoll();
  const toggle = useToggleActivePoll();

  const [form, setForm] = useState<PollForm>(EMPTY);
  const patch = (p: Partial<PollForm>) => setForm((prev) => ({ ...prev, ...p }));

  useEffect(() => {
    if (!detail.data) return;
    const p = detail.data;
    setForm({
      question: p.question,
      allow_multiple: p.allow_multiple,
      starts_at: toLocalInput(p.starts_at),
      ends_at: toLocalInput(p.ends_at),
      audience_mode: p.audience_mode,
      result_visibility: p.result_visibility,
      options: (p.options ?? []).map((o) => ({ id: o.id, label: o.label, votes_count: o.votes_count })),
    });
  }, [detail.data]);

  // ─── محرّر الخيارات ──────────────────────────────────────────────────────
  const setOption = (idx: number, label: string) =>
    setForm((prev) => ({ ...prev, options: prev.options.map((o, i) => (i === idx ? { ...o, label } : o)) }));
  const addOption = () => setForm((prev) => ({ ...prev, options: [...prev.options, { label: '', votes_count: 0 }] }));
  const removeOption = (idx: number) =>
    setForm((prev) => ({ ...prev, options: prev.options.filter((_, i) => i !== idx) }));
  const moveOption = (idx: number, dir: -1 | 1) =>
    setForm((prev) => {
      const next = [...prev.options];
      const target = idx + dir;
      if (target < 0 || target >= next.length) return prev;
      [next[idx], next[target]] = [next[target], next[idx]];
      return { ...prev, options: next };
    });

  const audienceOptions = useMemo(
    () => POLL_AUDIENCE_MODES.map((v) => ({ value: v, label: t(`audienceMode.${v}`) })),
    [t],
  );
  const visibilityOptions = useMemo(
    () => POLL_RESULT_VISIBILITIES.map((v) => ({ value: v, label: t(`resultVisibility.${v}`) })),
    [t],
  );

  const filledOptions = form.options.filter((o) => o.label.trim().length > 0);
  const canSave = form.question.trim().length >= 2 && filledOptions.length >= MIN_OPTIONS;
  const saving = create.isPending || update.isPending;

  const save = async () => {
    const payload: PollUpsertPayload = {
      question: form.question.trim(),
      allow_multiple: form.allow_multiple,
      starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : null,
      ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
      audience_mode: form.audience_mode,
      result_visibility: form.result_visibility,
      options: form.options
        .filter((o) => o.label.trim().length > 0)
        .map((o, i) => ({ ...(o.id ? { id: o.id } : {}), label: o.label.trim(), sort_order: i })),
    };
    try {
      if (isEdit) await update.mutateAsync({ id: pollId as number, payload });
      else await create.mutateAsync(payload);
      navigate(paths.polls);
    } catch {
      /* الخطأ يُعرَض عبر toast في الـ hook */
    }
  };

  if (isEdit && detail.isLoading) return <LoadingState />;

  const isActive = detail.data?.is_active ?? false;
  const state = detail.data?.state;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{t(isEdit ? 'polls.form.editTitle' : 'polls.form.createTitle')}</h1>
          {isEdit && state ? <Badge variant={STATE_TONE[state]}>{t(`pollState.${state}`)}</Badge> : null}
        </div>
        <Button variant="outline" size="sm" onClick={() => navigate(paths.polls)}>
          <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          {t('polls.form.back')}
        </Button>
      </header>

      {!isEdit ? (
        <p className="border border-border bg-muted/40 p-3 text-sm text-muted-foreground">{t('polls.form.inactiveNote')}</p>
      ) : null}

      {/* حالة التفعيل — للقراءة + زرّ نشر مستقلّ (polls.publish). */}
      {isEdit ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border border-border bg-background px-4 py-3.5">
          <div className="space-y-0.5">
            <p className="text-sm font-medium">{t('polls.form.activation')}</p>
            <p className="text-xs text-muted-foreground">
              {isActive ? t('polls.form.activeNow') : t('polls.form.inactiveNow')}
            </p>
          </div>
          {canPublish ? (
            <Button
              variant="outline"
              size="sm"
              disabled={toggle.isPending}
              onClick={() => toggle.mutate(pollId as number)}
            >
              <Power className="h-4 w-4" />
              {t(isActive ? 'polls.action.deactivate' : 'polls.action.activate')}
            </Button>
          ) : null}
        </div>
      ) : null}

      <TextField
        label={t('polls.form.question')}
        value={form.question}
        onChange={(e) => patch({ question: e.target.value })}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <TextField
          label={t('polls.form.startsAt')}
          type="datetime-local"
          value={form.starts_at}
          onChange={(e) => patch({ starts_at: e.target.value })}
        />
        <TextField
          label={t('polls.form.endsAt')}
          type="datetime-local"
          value={form.ends_at}
          onChange={(e) => patch({ ends_at: e.target.value })}
        />
        <SelectField
          label={t('polls.form.audienceMode')}
          options={audienceOptions}
          value={form.audience_mode}
          onChange={(e) => patch({ audience_mode: e.target.value as PollAudienceMode })}
        />
        <SelectField
          label={t('polls.form.resultVisibility')}
          options={visibilityOptions}
          value={form.result_visibility}
          onChange={(e) => patch({ result_visibility: e.target.value as PollResultVisibility })}
        />
      </div>

      <SwitchField
        label={t('polls.form.allowMultiple')}
        description={t('polls.form.allowMultipleHint')}
        checked={form.allow_multiple}
        onChange={(v) => patch({ allow_multiple: v })}
      />

      {/* محرّر الخيارات */}
      <div className="space-y-3 border border-border bg-background p-4">
        <div className="flex items-center justify-between gap-3">
          <div className="space-y-0.5">
            <p className="text-sm font-medium">{t('polls.form.options')}</p>
            <p className="text-xs text-muted-foreground">{t('polls.form.optionsHint')}</p>
          </div>
          <Button type="button" variant="outline" size="sm" onClick={addOption}>
            <Plus className="h-4 w-4" />
            {t('polls.form.addOption')}
          </Button>
        </div>

        <div className="space-y-2">
          {form.options.map((o, idx) => {
            const voted = (o.votes_count ?? 0) > 0;
            const canRemove = form.options.length > MIN_OPTIONS && !voted;
            return (
              <div key={o.id ?? `new-${idx}`} className="flex items-center gap-2">
                <div className="flex flex-col">
                  <button
                    type="button"
                    onClick={() => moveOption(idx, -1)}
                    disabled={idx === 0}
                    className="text-muted-foreground transition-colors hover:text-foreground disabled:opacity-30"
                    aria-label={t('polls.form.moveUp')}
                  >
                    <ChevronUp className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    onClick={() => moveOption(idx, 1)}
                    disabled={idx === form.options.length - 1}
                    className="text-muted-foreground transition-colors hover:text-foreground disabled:opacity-30"
                    aria-label={t('polls.form.moveDown')}
                  >
                    <ChevronDown className="h-4 w-4" />
                  </button>
                </div>
                <Input
                  value={o.label}
                  onChange={(e) => setOption(idx, e.target.value)}
                  placeholder={t('polls.form.optionPlaceholder', { n: idx + 1 })}
                  className="flex-1"
                />
                {voted ? (
                  <span className="whitespace-nowrap text-xs text-muted-foreground">
                    {t('polls.form.votesCount', { count: o.votes_count })}
                  </span>
                ) : null}
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-destructive disabled:opacity-30"
                  disabled={!canRemove}
                  title={voted ? t('polls.form.cannotRemoveVoted') : undefined}
                  onClick={() => removeOption(idx)}
                  aria-label={t('polls.action.delete')}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            );
          })}
        </div>
        <p className="text-xs text-muted-foreground">{t('polls.form.minOptions', { min: MIN_OPTIONS })}</p>
      </div>

      <div className="flex justify-end">
        <Button onClick={() => void save()} disabled={!canSave || saving}>
          <Save className="h-4 w-4" />
          {saving ? t('polls.form.saving') : t('polls.form.save')}
        </Button>
      </div>
    </div>
  );
}
