import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Loader2, User } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Input } from '@/components/ui/input';
import { storageUrl } from '@/lib/storage';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useChatContacts, useCreateConversation } from '../chat.hooks';

interface Props {
  open: boolean;
  onClose: () => void;
  onCreated: (conversationId: number) => void;
}

/**
 * بدء محادثة مباشرة — بحث في جهات الاتصال (المدراء) واختيار زميل. المجموعات لاحقاً.
 */
export function NewConversationModal({ open, onClose, onCreated }: Props) {
  const { t } = useTranslation('chat');
  const [search, setSearch] = useState('');
  const debounced = useDebouncedValue(search, 300);
  const contactsQ = useChatContacts(debounced, open);
  const create = useCreateConversation();

  const pick = (userId: number) => {
    create.mutate(
      { type: 'direct', user_ids: [userId] },
      {
        onSuccess: (conv) => {
          onCreated(conv.id);
          onClose();
          setSearch('');
        },
      },
    );
  };

  const contacts = contactsQ.data ?? [];

  return (
    <Modal open={open} onClose={onClose} title={t('modal.title')} size="md">
      <div className="space-y-3">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t('modal.searchPlaceholder')}
          autoFocus
        />

        {contactsQ.isLoading ? (
          <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            {t('modal.loading')}
          </p>
        ) : contacts.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            {search ? t('modal.empty') : t('modal.start')}
          </p>
        ) : (
          <div className="max-h-72 space-y-1 overflow-y-auto">
            {contacts.map((c) => (
              <button
                key={c.id}
                type="button"
                disabled={create.isPending}
                onClick={() => pick(c.id)}
                className="flex w-full items-center gap-3 p-2 text-start transition-colors hover:bg-accent/40 disabled:opacity-50"
              >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden bg-muted">
                  {storageUrl(c.avatar) ? (
                    <img src={storageUrl(c.avatar) ?? ''} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <User className="h-4 w-4 text-muted-foreground" />
                  )}
                </span>
                <span className="truncate text-sm font-medium">{c.name}</span>
              </button>
            ))}
          </div>
        )}
      </div>
    </Modal>
  );
}
