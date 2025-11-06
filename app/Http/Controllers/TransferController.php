<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Http\Requests\TransferRequest;
use App\Http\Requests\WithdrawRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;


class TransferController extends Controller
{
    public function deposit(DepositRequest $request) {

        if($request->validated()['user_id'] == 0){
            return response(['status' => 'User with id 0 was reserved. Use another one'],409);
        }
        $user = User::find($request->validated()['user_id']);
        if(is_null($user)){
            $userCreated =  new User();
            $userCreated->id = $request->validated()['user_id'];
            $userCreated->save();
            DB::table('user_status')->insert(
            ['user_id'=>$userCreated->id,
            'cash_amount'=> round($request->validated()['amount'],2)*100]);
            DB::table('payments_history')->insert([
                'from_user_id' => 0,
                'to_user_id' => $userCreated->id,
                'amount' => round($request->validated()['amount'],2)*100,
                'status' => 'deposit',
                'comment' => $request->validated()['comment']
            ]);
            return response(['status' => 'User with id '. $userCreated->id . ' was successfully created and a sum of ' . $request->validated()['amount'] . ' was successfully added to balance'],200);
        }
        else{
            DB::beginTransaction();
            $user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$user->id)->first();
            DB::table('user_status')->where('user_id',$user->id)->update([
                'cash_amount' => $user_status->cash_amount + round($request->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => 0,
                'to_user_id' => $user->id,
                'amount' => round($request->validated()['amount'],2)*100,
                'status' => 'deposit',
                'comment' => $request->validated()['comment']
            ]);
            DB::commit();
            return response(['status' => 'A sum of' .$request->validated()['amount']. 'was succesfully added to user ' . $user->id . ' account'],200);
        }
        }

    public function withdraw(WithdrawRequest $request){

    
        $user = User::find($request->validated()['user_id']);

        if(is_null($user)){
            return response(['status' => 'User with id '. $request->validated()['user_id'] . ' wasnt found'],404);
        }
        DB::beginTransaction();
        $user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$user->id)->first();
        if($user_status->cash_amount - round($request->validated()['amount']*100) < 0){
             DB::rollBack();
             return response(['status' => 'User with id '. $request->validated()['user_id'] . ' doesnt have '. $request->validated()['amount'].' on balance'],409);
        }
        else{
            DB::table('user_status')->where('user_id',$user->id)->update([
                'cash_amount' => $user_status->cash_amount - round($request->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $user->id,
                'to_user_id' => 0,
                'amount' => round($request->validated()['amount'],2)*100,
                'status' => 'withdraw',
                'comment' => $request->validated()['comment']
            ]);
            DB::commit();
            return response(['status' => 'A sum of' .$request->validated()['amount']. 'was succesfully removed from user ' . $user->id . ' account'],200);
        }

    }
    public function transfer(TransferRequest $request){
        

        $first_user = User::find($request->validated()['from_user_id']);

        if(is_null($first_user)){
            return response(['status' => 'From User with id '. $request->validated()['from_user_id'] . ' wasnt found'],404);
        }

        $second_user = User::find($request->validated()['to_user_id']);

        if(is_null($second_user)){
            return response(['status' => 'To User with id '. $request->validated()['to_user_id'] . ' wasnt found'],404);
        }

        
        DB::beginTransaction();
        $first_user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$first_user->id)->first();
        if($first_user_status->cash_amount - round($request->validated()['amount']*100,2) < 0){
             DB::rollBack();
             return response(['status' => 'From User with id '. $request->validated()['from_user_id'] . ' doesnt have '. $request->validated()['amount'].' on balance'],409);
        }
        else{
            $second_user_status = DB::table('user_status')->lockForUpdate()->where('user_id',$second_user->id)->first();
            DB::table('user_status')->where('user_id',$first_user->id)->update([
                'cash_amount' => $first_user_status->cash_amount - round($request->validated()['amount']*100,2)
            ]);
            
            DB::table('user_status')->where('user_id',$second_user->id)->update([
                'cash_amount' => $second_user_status->cash_amount + round($request->validated()['amount'],2)*100
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $first_user->id,
                'to_user_id' => $second_user->id,
                'amount' => round($request->validated()['amount'],2)*100,
                'status' => 'transfer_out',
                'comment' => $request->validated()['comment']
            ]);
            DB::table('payments_history')->insert([
                'from_user_id' => $first_user->id,
                'to_user_id' => $second_user->id,
                'amount' => round($request->validated()['amount'],2)*100,
                'status' => 'transfer_in',
                'comment' => $request->validated()['comment']
            ]);

            DB::commit();
            return response(['status' => 'Sum of ' .$request->validated()['amount']. 'was succesfully transfered from user' .$first_user->id.'to user ' .$second_user->id ],200);
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
