export interface AdminUser {
  id: number;
  name: string;
  email: string;
  status: string;
  email_verified: boolean;
  avatar: string | null;
  roles: string[];
  permissions: string[];
  last_login_at: string | null;
}

export interface AuthTokenPayload {
  token: string;
  user: Pick<AdminUser, 'id' | 'name' | 'email' | 'status'> & { roles: string[] };
}
