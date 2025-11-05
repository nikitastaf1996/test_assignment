<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    public function deposit(Request $request) {

        $validator = Validator::make($request->all(),[
            'user_id' => ['required','integer'],
            'amount' => ['required','numeric'],
            'comment' => ['nullable','string']
        ]);
    
        if($validator->fails()){
            return response()->json([
                'errors'=>$validator->errors()],422);
        }

        if($validator->validated()['user_id'] == 0){
            return response(['status' => 'User with id 0 was reserved. Use another one'],409);
        }
        $user = User::find($validator->validated()['user_id']);
        if(is_null($user)){
            $userCreated =  new User();
            $userCreated->id = $validator->validated()['user_id'];
            $userCreated->save();
            DB::table('user_status')->insert(
            ['user_id'=>$userCreated->id,
            'cash_amount'=> round($validator->validated()['amount'],2)*100]);
            DB::table('payments_history')->insert([
                'from_user_id' => 0,
                'to_user_id' => $userCreated->id,
                'amount' => round($validator->validated()['amount'],2)*100,
                'status' => 'deposit',
                'comment' => $validator->validated()['comment']
            ]);
            return response(['status' => 'User with id '. $userCreated->id . ' was successfully created and a sum of ' . $validator->validated()['amount'] . ' was successfully added to balance'],200);
        }
        else{
            DB::beginTransaction();
            $user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$user->id)->first();
            DB::table('user_status')->where('user_id',$user->id)->update([
                'cash_amount' => $user_status->cash_amount + round($validator->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => 0,
                'to_user_id' => $user->id,
                'amount' => round($validator->validated()['amount'],2)*100,
                'status' => 'deposit',
                'comment' => $validator->validated()['comment']
            ]);
            DB::commit();
            return response(['status' => 'A sum of' .$validator->validated()['amount']. 'was succesfully added to user ' . $user->id . ' account'],200);
        }
        }

    public function withdraw(Request $request){

        $validator = Validator::make($request->all(),[
            'user_id' => ['required','integer'],
            'amount' => ['required','numeric'],
            'comment' => ['nullable','string']
        ]);

        if($validator->fails()){
            return response()->json([
                'errors'=>$validator->errors()],422);
        }

        $user = User::find($validator->validated()['user_id']);

        if(is_null($user)){
            return response(['status' => 'User with id '. $validator->validated()['user_id'] . ' wasnt found'],404);
        }
        DB::beginTransaction();
        $user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$user->id)->first();
        if($user_status->cash_amount - round($validator->validated()['amount']*100) < 0){
             DB::rollBack();
             return response(['status' => 'User with id '. $validator->validated()['user_id'] . ' doesnt have '. $validator->validated()['amount'].' on balance'],409);
        }
        else{
            DB::table('user_status')->where('user_id',$user->id)->update([
                'cash_amount' => $user_status->cash_amount - round($validator->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $user->id,
                'to_user_id' => 0,
                'amount' => round($validator->validated()['amount'],2)*100,
                'status' => 'withdraw',
                'comment' => $validator->validated()['comment']
            ]);
            DB::commit();
            return response(['status' => 'A sum of' .$validator->validated()['amount']. 'was succesfully removed from user ' . $user->id . ' account'],200);
        }

    }
    public function transfer(Request $request){
        $validator = Validator::make($request->all(),[
            'from_user_id' => ['required','integer'],
            'to_user_id' => ['required','integer'],
            'amount' => ['required','numeric'],
            'comment' => ['nullable','string']
        ]);

        if($validator->fails()){
            return response()->json([
                'errors'=>$validator->errors()],422);
        }

        $first_user = User::find($validator->validated()['from_user_id']);

        if(is_null($first_user)){
            return response(['status' => 'From User with id '. $validator->validated()['from_user_id'] . ' wasnt found'],404);
        }

        $second_user = User::find($validator->validated()['to_user_id']);

        if(is_null($second_user)){
            return response(['status' => 'To User with id '. $validator->validated()['to_user_id'] . ' wasnt found'],404);
        }

        
        DB::beginTransaction();
        $first_user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$first_user->id)->first();
        if($first_user_status->cash_amount - round($validator->validated()['amount']*100,2) < 0){
             DB::rollBack();
             return response(['status' => 'From User with id '. $validator->validated()['from_user_id'] . ' doesnt have '. $validator->validated()['amount'].' on balance'],409);
        }
        else{
            $second_user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$second_user->id)->first();
            DB::table('user_status')->where('user_id',$first_user->id)->update([
                'cash_amount' => $first_user_status->cash_amount - round($validator->validated()['amount']*100,2)
            ]);
            
            DB::table('user_status')->where('user_id',$second_user->id)->update([
                'cash_amount' => $second_user_status->cash_amount + round($validator->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $first_user->id,
                'to_user_id' => $second_user->id,
                'amount' => round($validator->validated()['amount'],2)*100,
                'status' => 'transfer_out',
                'comment' => $validator->validated()['comment']
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $first_user->id,
                'to_user_id' => $second_user->id,
                'amount' => round($validator->validated()['amount'],2)*100,
                'status' => 'transfer_in',
                'comment' => $validator->validated()['comment']
            ]);

            DB::commit();
            return response(['status' => 'Sum of ' .$validator->validated()['amount']. 'was succesfully transfered from user' .$first_user->id.'to user ' .$second_user->id ],200);
        }



    }
    public function balance(User $user){
        $user_status = DB::table('user_status')->where('user_id',$user->id)->first();
        return response()->json([
            'user_id' => $user_status->user_id,
            'balance' => $user_status->cash_amount / 100
        ]);
    }
}
