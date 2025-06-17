<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceReqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'work_processing_start_time' => 'required|date_format:H:i',
            'work_processing_end_time' => 'nullable|date_format:H:i|required_with:work_processing_start_time|after:work_processing_start_time',
            'rest_processing_start_time' => 'nullable|array',
            'rest_processing_end_time' => 'nullable|array',
            'rest_processing_start_time.*' => 'nullable|date_format:H:i|required_with:rest_processing_end_time.*|after:work_processing_start_time|before:work_processing_end_time',
            'rest_processing_end_time.*' => 'nullable|date_format:H:i|required_with:rest_processing_start_time.*|after:rest_processing_start_time.*|before:work_processing_end_time',    
            'comment' => 'required',
        ];
    }

    public function messages()
    {
        return [
        'work_processing_start_time.required' => '出勤時間を入力してください',
        'work_processing_end_time.required_with' => '出勤時間が指定されている場合は、退勤時間も入力してください。',
        'work_processing_start_time.date_format' => '出勤時間は時間形式で入力してください',
        'work_processing_end_time.date_format' => '退勤時間は時間形式で入力してください',
        'work_processing_end_time.after' => '出勤時間もしくは退勤時間が不適切な値です',
        'rest_processing_start_time.*.date_format' => '休憩開始時間は時間形式で入力してください',
        'rest_processing_start_time.*.after' => '休憩時間が勤務時間外です',
        'rest_processing_start_time.*.before' => '休憩時間が勤務時間外です',
        'rest_processing_start_time.*.required_with' => '休憩開始時間を入力してください',
        'rest_processing_end_time.*.before' => '休憩時間が勤務時間外です',
        'rest_processing_end_time.*.date_format' => '休憩終了時間は時間形式で入力してください',
        'rest_processing_end_time.*.required_with' => '休憩開始時間が指定されている場合は、休憩終了時間も入力してください。',
        'rest_processing_end_time.*.after' => '休憩開始時間もしくは休憩終了時間が不適切な値です',
        'comment.required' => '備考を入力してください',
        ];
    }
}
