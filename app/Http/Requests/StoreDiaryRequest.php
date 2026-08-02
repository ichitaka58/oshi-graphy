<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Override;

class StoreDiaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 実際の認可（誰がどの日記を操作できるか）はコントローラーでGate::authorize()が担当
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'happened_on' => ['required', 'date'],
            // exists:artists,id→存在しないartist_idが入らないようにする
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'body' => ['required', 'string'],
            'images' => ['nullable', 'array'],
            // images.*とすることで,複数ファイル（配列）をチェック可能
            // image:画像かどうか、mimes:許可する拡張子、max:5120 5MB
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'is_public' => ['boolean']
        ];
    }

    public function messages()
    {
        return [
            'happened_on.required' => '日付を入力してください',
            'happened_on.date' => '日付は日付形式で入力してください',
            'artist_id.required' => 'アーティストを入力してください',
            'body.required' => '本文を入力してください',
            'body.string' => '本文は文字列で入力してください',
            'images.*.image' => '写真は画像ファイルを添付してください',
            'images.*.mimes' => '写真はjpeg、jpg、png、webp形式で添付してください',
            'images.*.max' => '写真のサイズは5MB以下にしてください',
        ];
    }

    // デフォルトはリダイレクトを返すが、APIはJSONが必要なためオーバーライド
    // #[Override]：PHP 8.3のアトリビュート。親クラスに同名メソッドがなければエラーになり、タイポを防ぐ
    #[Override]
    protected function failedValidation(Validator $validator)
    {
        if($this->expectsJson()) {
            $response = response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);

            throw new HttpResponseException($response);
        }
        return parent::failedValidation($validator);
    }
}
