/** عقد استجابة الـ backend الموحّد (ApiResponse) */
export interface ApiSuccess<T> {
  success: true;
  message: string;
  data: T;
  meta: Record<string, unknown>;
}

export interface ApiError {
  success: false;
  message: string;
  errors: Record<string, string[]>;
}

export type ApiResponse<T> = ApiSuccess<T> | ApiError;

/** خطأ موحّد يُرمى من طبقة الـ HTTP */
export interface NormalizedError {
  status: number;
  message: string;
  errors: Record<string, string[]>;
}
